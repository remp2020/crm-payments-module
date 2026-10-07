<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Models\Subscription\StopSubscriptionHandler;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use League\Event\AbstractListener;
use Mockery;
use Nette\Database\Table\ActiveRow;

class SubscriptionPauserStopResumedTest extends PaymentsTestCase
{
    private const string SPLIT_NOTE = '[pause] Subscription splitted from #';
    private const string STOPPED_NOTE = '[pause] Subscription stopped with paused subscription #';

    private SubscriptionsRepository $subscriptionsRepository;
    private SubscriptionPausesRepository $subscriptionPausesRepository;
    private SubscriptionMetaRepository $subscriptionMetaRepository;
    private SubscriptionPauser $subscriptionPauser;
    private SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager;
    private LazyEventEmitter $lazyEventEmitter;

    public function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            SubscriptionPausesRepository::class,
            SubscriptionMetaRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionPausesRepository = $this->getRepository(SubscriptionPausesRepository::class);
        $this->subscriptionMetaRepository = $this->getRepository(SubscriptionMetaRepository::class);
        $this->subscriptionPauser = $this->inject(SubscriptionPauser::class);
        $this->subscriptionEndsSuppressionManager = $this->inject(SubscriptionEndsSuppressionManager::class);
        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->lazyEventEmitter->removeAllListeners(SubscriptionShortenedEvent::class);
        $this->subscriptionPauser->setNow(null);

        parent::tearDown();
    }

    public function testPauseScheduledAndPausedStoppedCollapsesResumedToNewEnd(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));
        $resumed = $this->pauseSubscription($paused, new DateTime('2023-01-20'), new DateTime('2023-02-10'));

        $now = new DateTime('2023-01-15');
        // admin refund form stops the paused subscription before the refund status event fires
        $this->subscriptionsRepository->update($paused, ['end_time' => $now]);

        $this->subscriptionPauser->setNow($now);
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertCollapsedAt($now, $resumed);
        $this->assertStringContainsString(self::SPLIT_NOTE . $paused->id, $resumed->note);
        $this->assertStringContainsString(self::STOPPED_NOTE . $paused->id, $resumed->note);
        $this->assertTrue($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($resumed));
        $this->assertEquals($now, $this->subscriptionsRepository->find($paused->id)->end_time);
    }

    public function testPauseInEffectCollapsesResumedToNow(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));
        $resumed = $this->pauseSubscription($paused, new DateTime('2023-01-10'), new DateTime('2023-01-31'));

        $now = new DateTime('2023-01-15');
        $this->subscriptionPauser->setNow($now);
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertCollapsedAt($now, $resumed);
        $this->assertTrue($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($resumed));
        $this->assertEquals(new DateTime('2023-01-10'), $this->subscriptionsRepository->find($paused->id)->end_time);
        $this->assertSame('1', $this->subscriptionMetaRepository->getMetaValue($resumed, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN));
    }

    public function testPauseOverStopsActiveResumedNowAndEmitsShortenedEvent(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));
        $resumed = $this->pauseSubscription($paused, new DateTime('2023-01-10'), new DateTime('2023-01-31'));

        // following subscriptions are realigned by the SubscriptionShortenedEvent listeners, not by the pauser
        $shortenedListener = Mockery::mock(AbstractListener::class)
            ->shouldReceive('handle')
            ->once()
            ->withArgs(function (SubscriptionShortenedEvent $event) use ($resumed) {
                return $event->getBaseSubscription()->id === $resumed->id
                    && $event->getOriginalEndTime() == new DateTime('2023-02-21');
            })
            ->getMock();
        $this->lazyEventEmitter->addListener(SubscriptionShortenedEvent::class, $shortenedListener);

        $now = new DateTime('2023-02-05');
        $this->subscriptionPauser->setNow($now);
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertEquals(new DateTime('2023-01-31'), $resumed->start_time);
        $this->assertEquals($now, $resumed->end_time);
        $this->assertStringContainsString(self::STOPPED_NOTE . $paused->id, $resumed->note);
        $this->assertFalse($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($resumed));
        $this->assertSame('1', $this->subscriptionMetaRepository->getMetaValue($resumed, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN));
    }

    public function testAlreadyStoppedResumedIsUntouched(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));
        $resumed = $this->pauseSubscription($paused, new DateTime('2023-01-10'), new DateTime('2023-01-31'));
        $this->subscriptionsRepository->update($resumed, ['end_time' => $resumed->start_time]);
        $noteBefore = $resumed->note;

        $this->subscriptionPauser->setNow(new DateTime('2023-01-15'));
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertCollapsedAt(new DateTime('2023-01-31'), $resumed);
        $this->assertSame($noteBefore, $resumed->note);
        $this->assertFalse($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($resumed));
    }

    public function testChainedPausesStopBothResumed(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-03-01'));
        $resumed = $this->pauseSubscription($paused, new DateTime('2023-01-10'), new DateTime('2023-01-20'));
        $resumedAgain = $this->pauseSubscription($resumed, new DateTime('2023-02-01'), new DateTime('2023-02-08'));

        $now = new DateTime('2023-01-15');
        $this->subscriptionPauser->setNow($now);
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertCollapsedAt($now, $resumed);
        $this->assertStringContainsString(self::STOPPED_NOTE . $paused->id, $resumed->note);

        $resumedAgain = $this->subscriptionsRepository->find($resumedAgain->id);
        $this->assertCollapsedAt($now, $resumedAgain);
        $this->assertStringContainsString(self::STOPPED_NOTE . $resumed->id, $resumedAgain->note);
    }

    public function testPauseWithoutResumedSubscriptionLinkChangesNothing(): void
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));
        $unlinked = $this->createSubscription(new DateTime('2023-01-31'), new DateTime('2023-02-21'));
        $pause = $this->subscriptionPausesRepository->add($paused, new DateTime('2023-01-10'), new DateTime('2023-01-31'), [], $unlinked->id);
        $this->subscriptionPausesRepository->update($pause, ['resumed_subscription_id' => null]);

        $this->subscriptionPauser->setNow(new DateTime('2023-01-15'));
        $this->subscriptionPauser->stopResumedSubscriptions($paused);

        $unlinked = $this->subscriptionsRepository->find($unlinked->id);
        $this->assertEquals(new DateTime('2023-01-31'), $unlinked->start_time);
        $this->assertEquals(new DateTime('2023-02-21'), $unlinked->end_time);
    }

    public function testSubscriptionWithoutPauseChangesNothing(): void
    {
        $subscription = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));

        $this->subscriptionPauser->setNow(new DateTime('2023-01-15'));
        $this->subscriptionPauser->stopResumedSubscriptions($subscription);

        $subscription = $this->subscriptionsRepository->find($subscription->id);
        $this->assertEquals(new DateTime('2023-01-01'), $subscription->start_time);
        $this->assertEquals(new DateTime('2023-01-31'), $subscription->end_time);
    }

    private function assertCollapsedAt(DateTime $expected, ActiveRow $subscription): void
    {
        $this->assertEquals($expected, $subscription->start_time);
        $this->assertEquals($expected, $subscription->end_time);
    }
}
