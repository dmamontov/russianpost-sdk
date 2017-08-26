<?php

/**
 * This file is part of RussianPost SDK package.
 *
 * © Appwilio (http://appwilio.com), greabock (https://github.com/greabock), JhaoDa (https://github.com/jhaoda)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Appwilio\RussianPostSDK\Tracking;

use Appwilio\RussianPostSDK\Tracking\Packet\TicketResponse;
use Appwilio\RussianPostSDK\Tracking\Packet\TrackingResponse;
use Appwilio\RussianPostSDK\Tracking\Exceptions\PacketAccessException;

class PacketAccessClient
{
    public const LANG_ENG = 'ENG';
    public const LANG_RUS = 'RUS';

    public const HISTORY_OPERATIONS = 0;
    public const HISTORY_NOTIFICATIONS = 1;

    public const ERROR_NOT_READY = 6;
    public const ERROR_INTERNAL = 16;

    protected const TRACKING_WSDL_URL = 'https://tracking.russianpost.ru/fc?wsdl';

    /** @var string */
    protected $login;

    /** @var string */
    protected $password;

    /** @var \SoapClient */
    protected $client;

    protected $options = [
        'soap_version' => SOAP_1_1,
        'trace'        => 1,
        'classmap'     => [
            'item'                   => Packet\Item::class,       // Item согласно wsdl-описанию называется item
            'file'                   => Packet\Value::class,      // value согласно wsdl-описанию называется file
            'error'                  => Packet\Error::class,      // корневая ошибка
            'Error'                  => Packet\Error::class,      // ошибка конкретного трека
            'operation'              => Packet\Operation::class,  // Operation согласно wsdl-описанию называется operation
            'ticketResponse'         => TicketResponse::class,
            'answerByTicketResponse' => TrackingResponse::class,
        ],
    ];

    public function __construct(string $login, string $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    public function getTicket(iterable $tracks, string $language = self::LANG_RUS): TicketResponse
    {
        if (is_array($tracks)) {
            $tracks = new \ArrayIterator($tracks);
        }

        if (iterator_count($tracks) > 3000) {
            throw PacketAccessException::tracksNumberLimitExceeded();
        }

        $arguments = $this->assembleTicketRequestArguments($tracks, $language);

        /** @var TicketResponse $response */
        $response = $this->getClient()->{'getTicket'}($arguments);

        if ($response->hasError()) {
            throw new PacketAccessException($response->getError()->getMessage(), $response->getError()->getCode());
        }

        return $response;
    }

    public function getTrackingEvents(string $ticket): TrackingResponse
    {
        $arguments = $this->assembleTrackingHistoryRequestArgument($ticket);

        /** @var TrackingResponse $response */
        $response = $this->getClient()->{'getResponseByTicket'}($arguments);

        if ($response->hasError()) {
            throw new PacketAccessException($response->getError()->getMessage(), $response->getError()->getCode());
        }

        return $response;
    }

    private function assembleTicketRequestArguments(iterable $codes, string $language): array
    {
        $items = new \ArrayObject();

        foreach ($codes as $code) {
            $items->append(new \SoapVar("<Item Barcode=\"{$code}\" />", XSD_ANYXML));
        }

        return [
            new \SoapVar([
                new \SoapVar($items, SOAP_ENC_OBJECT, null, null, 'request'),
                new \SoapVar($this->login, XSD_STRING, null, null, 'login'),
                new \SoapVar($this->password, XSD_STRING, null, null, 'password'),
                new \SoapVar($language, XSD_STRING, null, null, 'language'),
            ], SOAP_ENC_OBJECT),
        ];
    }

    private function assembleTrackingHistoryRequestArgument(string $ticket): array
    {
        return [
            new \SoapVar([
                new \SoapVar($ticket, XSD_STRING, null, null, 'ticket'),
                new \SoapVar($this->login, XSD_STRING, null, null, 'login'),
                new \SoapVar($this->password, XSD_STRING, null, null, 'password'),
            ], SOAP_ENC_OBJECT),
        ];
    }

    public function getClient(): \SoapClient
    {
        if (! $this->client) {
            $this->client = new \SoapClient(self::TRACKING_WSDL_URL, $this->options);
        }

        return $this->client;
    }
}