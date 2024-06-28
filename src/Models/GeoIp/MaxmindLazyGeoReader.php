<?php

namespace Crm\PaymentsModule\Models\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Tracy\Debugger;
use Tracy\ILogger;

final class MaxmindLazyGeoReader implements GeoIpInterface
{
    private ?Reader $reader = null;

    public function __construct(
        private ?string $maxmindDatabasePath = null
    ) {
    }

    private function maxmindReader(): ?Reader
    {
        if (!$this->reader) {
            if (!$this->maxmindDatabasePath) {
                return null;
            }

            try {
                $this->reader = new Reader($this->maxmindDatabasePath);
            } catch (\Exception $e) {
                Debugger::log($e, ILogger::ERROR);
                return null;
            }
        }
        return $this->reader;
    }

    public function setMaxmindDatabasePath(string $maxmindDatabasePath): void
    {
        $fullMaxmindDatabasePath = realpath($maxmindDatabasePath);
        if (!$maxmindDatabasePath) {
            throw new GeoIpException("Unable to initialize GeoIP database, path '{$maxmindDatabasePath}' does not exist");
        }

        $this->maxmindDatabasePath = $fullMaxmindDatabasePath;
        if ($this->reader) {
            $this->maxmindReader(); // refresh Reader
        }
    }

    /**
     * @param string $ip
     *
     * @return string|null
     * @throws GeoIpException
     */
    public function countryCode(string $ip): ?string
    {
        if (!$this->maxmindReader()) {
            throw new GeoIpException("Unable to resolve country for {$ip}, Maxmind reader was not initialized correctly, see error log.");
        }

        try {
            $record = $this->maxmindReader()->country($ip);
        } catch (AddressNotFoundException|InvalidDatabaseException|\InvalidArgumentException $e) {
            throw new GeoIpException("Unable to resolve country for {$ip}.", 0, $e);
        }

        return $record->country->isoCode;
    }
}
