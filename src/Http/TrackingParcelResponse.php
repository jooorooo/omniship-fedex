<?php
/**
 * Created by PhpStorm.
 * User: joro
 * Date: 10.5.2017 г.
 * Time: 17:22 ч.
 */

namespace Omniship\FedEx\Http;

use Carbon\Carbon;
use Omniship\Common\Component;
use Omniship\Common\EventBag;
use Omniship\Common\TrackingBag;
use Omniship\Message\AbstractResponse;

class TrackingParcelResponse extends AbstractResponse
{
    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * @return TrackingBag
     */
    public function getData()
    {
        $result = new TrackingBag();
        if(!is_null($this->getCode())) {
            return $result;
        }

        if(!empty($this->data->TrackDetails)) {
            foreach($this->data->TrackDetails AS $quote) {
                $result->add([
                    'id' => $quote->TrackingNumberUniqueIdentifier,
                    'name' => ($name = (!empty($quote->DestinationAddress) ? $this->_getDestinationAddress($quote->DestinationAddress) : null)),
                    'events' => null,//$this->_getEvents($quote),
                    'shipment_date' => Carbon::createFromFormat('Y-m-d\TH:i:s', $quote->ShipTimestamp),
                    'estimated_delivery_date' => !empty($quote->ActualDeliveryTimestamp) ? Carbon::createFromFormat('Y-m-d\TH:i:sP', $quote->ActualDeliveryTimestamp) : null,
                    'origin_service_area' => null,
                    'destination_service_area' => $name ? new Component(['id' => md5(json_encode($quote->DestinationAddress)), 'name' => $name]) : null,
                ]);
            }
        }
        return $result;
    }

    /**
     * @return null|string
     */
    public function getMessage()
    {
        if(!empty($this->data->Notifications)) {
            if(is_array($this->data->Notifications)) {
                foreach ($this->data->Notifications AS $notification) {
                    if ($notification->Severity == 'ERROR' || empty($this->data->RateReplyDetails)) {
                        return $notification->LocalizedMessage;
                    }
                }
            } elseif(empty($this->data->TrackDetails)) {
                return $this->data->Notifications->LocalizedMessage;
            }
        }
        return null;
    }

    /**
     * @return null|string
     */
    public function getCode()
    {
        if(!empty($this->data->Notifications)) {
            if(is_array($this->data->Notifications)) {
                foreach ($this->data->Notifications AS $notification) {
                    if ($notification->Severity == 'ERROR') {
                        return $notification->Code;
                    }
                }
            } elseif(empty($this->data->TrackDetails)) {
                return $this->data->Notifications->Code;
            }
        }
        return null;
    }

    /**
     * @param $xml
     * @return EventBag
     */
    protected function _getEvents($xml)
    {
        $result = new EventBag();
        if($xml->ShipmentEvent) {
            foreach($xml->ShipmentEvent AS $event) {
                $result->add(new Component([
                    'id' => (string)$event->ServiceEvent->EventCode,
                    'name' => (string)$event->ServiceEvent->Description,
                ]));
            }
        }
        return $result;
    }

    protected function _getDestinationAddress($data) {
        $return = [];
        if(!empty($data->CountryCode)) {
            $return[] = $data->CountryCode;
            if(!empty($data->StateOrProvinceCode)) {
                $return[] = $data->StateOrProvinceCode;
                if(!empty($data->City)) {
                    $return[] = $data->City;
                    if(!empty($data->Residential)) {
                        $return[] = $data->Residential;
                    }
                }
            }
        }
        return implode(', ', $return);
    }

}