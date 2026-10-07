<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddResumedSubscriptionIdToSubscriptionPauses extends AbstractMigration
{
    public function up(): void
    {
        $this->table('subscription_pauses')
            ->addColumn('resumed_subscription_id', 'integer', ['null' => true, 'after' => 'subscription_id'])
            ->addForeignKey('resumed_subscription_id', 'subscriptions', 'id')
            ->update();

        // Older pauses have no link, only the note "[pause] Subscription splitted from #<id>"
        // in the resumed subscription. Match the note exactly or followed by a newline,
        // a plain LIKE '#12%' would also match #123. If the resumed subscription was paused
        // again, its note was overwritten, so fall back to the subscription starting exactly
        // at resume_at, only the resumed one starts at that time.
        $this->execute(<<<SQL
            UPDATE subscription_pauses sp
            JOIN subscriptions paused ON paused.id = sp.subscription_id
            JOIN subscriptions resumed ON resumed.user_id = paused.user_id
                AND (
                    resumed.note = CONCAT('[pause] Subscription splitted from #', sp.subscription_id)
                    OR resumed.note LIKE CONCAT('[pause] Subscription splitted from #', sp.subscription_id, '\\n%')
                    OR (resumed.start_time = sp.resume_at AND resumed.note LIKE '[pause] %')
                )
            SET sp.resumed_subscription_id = resumed.id
            WHERE sp.resumed_subscription_id IS NULL
        SQL);
    }

    public function down(): void
    {
        $this->table('subscription_pauses')
            ->dropForeignKey('resumed_subscription_id')
            ->removeColumn('resumed_subscription_id')
            ->update();
    }
}
