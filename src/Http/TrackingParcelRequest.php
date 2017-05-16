<?php
/**
 * Created by PhpStorm.
 * User: joro
 * Date: 12.5.2017 г.
 * Time: 18:03 ч.
 */

namespace Omniship\FedEx\Http;

use FedEx\TrackService\Request;
use FedEx\TrackService\ComplexType;
use FedEx\TrackService\SimpleType\EMailNotificationFormatType;

class TrackingParcelRequest extends AbstractRequest
{
    /**
     * @return ComplexType\TrackNotificationRequest
     */
    public function getData() {
        $trackNotificationRequest = new ComplexType\TrackNotificationRequest();

        //authentication & client details
        $trackNotificationRequest->WebAuthenticationDetail->UserCredential->Key = $this->getKey();
        $trackNotificationRequest->WebAuthenticationDetail->UserCredential->Password = $this->getPassword();
        $trackNotificationRequest->ClientDetail->AccountNumber = $this->getUsername();
        $trackNotificationRequest->ClientDetail->MeterNumber = $this->getMeter();

        //version
        $trackNotificationRequest->Version->ServiceId = 'trck';
        $trackNotificationRequest->Version->Major = 5;
        $trackNotificationRequest->Version->Minor = 0;
        $trackNotificationRequest->Version->Intermediate = 0;

        $trackNotificationRequest->setSenderEMailAddress('test@test.com');
        $trackNotificationRequest->setSenderContactName('mr Test');
        $trackNotificationRequest->TrackingNumber = $this->getParcelId();

        $notification_detail = new ComplexType\EMailNotificationDetail();

        $recipient = new ComplexType\EMailNotificationRecipient();
        $recipient->EMailAddress = 'test2@test.com';
        $recipient->Format = EMailNotificationFormatType::_HTML;
        $notification_detail->setRecipients([$recipient]);
        $trackNotificationRequest->setNotificationDetail($notification_detail);

        return $trackNotificationRequest;
    }

    public function sendData($data) {
        $rateServiceRequest = new Request();
        $rateServiceRequest->getSoapClient()->__setLocation($this->getEndpoint());
        $response = $rateServiceRequest->getGetTrackNotificationReply($data);

        return $this->createResponse($response);
    }

    /**
     * @param $data
     * @return TrackingParcelResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new TrackingParcelResponse($this, $data);
    }

    /**
     * Get url associated to a specific service
     *
     * @return string URL for the service
     */
    public function getEndpoint()
    {
        return $this->getTestMode() ? Request::TESTING_URL : Request::PRODUCTION_URL;
    }
}