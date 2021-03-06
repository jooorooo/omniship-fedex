<?php
/**
 * Created by PhpStorm.
 * User: joro
 * Date: 10.5.2017 г.
 * Time: 16:55 ч.
 */

namespace Omniship\FedEx\Http;

use FedEx\RateService\ComplexType;
use FedEx\RateService\Request;
use FedEx\RateService\SimpleType;
use Omniship\Common\ItemBag;
use Omniship\FedEx\Helper\Convert;
use Crisu83\Conversion\Quantity\Mass\Unit AS MassUnit;
use Crisu83\Conversion\Quantity\Length\Unit AS LengthUnit;

class ShippingQuoteRequest extends AbstractRequest
{
    /**
     * @return ComplexType\RateRequest
     */
    public function getData() {
        $convert = new Convert();

        $rateRequest = new ComplexType\RateRequest();

        //authentication & client details
        $rateRequest->WebAuthenticationDetail->UserCredential->Key = $this->getKey();
        $rateRequest->WebAuthenticationDetail->UserCredential->Password = $this->getPassword();
        $rateRequest->ClientDetail->AccountNumber = $this->getUsername();
        $rateRequest->ClientDetail->MeterNumber = $this->getMeter();
        $rateRequest->ClientDetail->Localization->LanguageCode = $this->getLanguageCode();

        $rateRequest->TransactionDetail->CustomerTransactionId = $this->getTransactionId();

        //version
        $rateRequest->Version->ServiceId = 'crs';
        $rateRequest->Version->Major = 10;
        $rateRequest->Version->Minor = 0;
        $rateRequest->Version->Intermediate = 0;

        $rateRequest->ReturnTransitAndCommit = true;

        //Shipper
        $shipping_address = $this->getSenderAddress();
        $rateRequest->RequestedShipment->Shipper->Address->StreetLines = array_filter([$shipping_address->getAddress1(), $shipping_address->getAddress2(), $shipping_address->getAddress3()]);
        $rateRequest->RequestedShipment->Shipper->Address->City = $shipping_address->getCity()->getName();
        if($shipping_address->getState()) {
            $rateRequest->RequestedShipment->Shipper->Address->StateOrProvinceCode = $shipping_address->getState()->getIso2();
        }
        $rateRequest->RequestedShipment->Shipper->Address->PostalCode = $shipping_address->getPostCode();
        $rateRequest->RequestedShipment->Shipper->Address->CountryCode = $shipping_address->getCountry()->getIso2();

        //Recipient
        $receiver_address = $this->getReceiverAddress();
        $rateRequest->RequestedShipment->Recipient->Address->StreetLines = array_filter([$receiver_address->getAddress1(), $receiver_address->getAddress2(), $receiver_address->getAddress3()]);
        $rateRequest->RequestedShipment->Recipient->Address->City = $receiver_address->getCity()->getName();
        if($receiver_address->getState()) {
            $rateRequest->RequestedShipment->Recipient->Address->StateOrProvinceCode = $receiver_address->getState()->getIso2();
        }
        $rateRequest->RequestedShipment->Recipient->Address->PostalCode = $receiver_address->getPostCode();
        $rateRequest->RequestedShipment->Recipient->Address->CountryCode = $receiver_address->getCountry()->getIso2();

        //shipping charges payment
        $rateRequest->RequestedShipment->ShippingChargesPayment->PaymentType = SimpleType\PaymentType::_SENDER;
        $rateRequest->RequestedShipment->ShippingChargesPayment->Payor->AccountNumber = $this->getUsername();
        $rateRequest->RequestedShipment->ShippingChargesPayment->Payor->CountryCode = $shipping_address->getCountry()->getIso2();

        //rate request types
//        $rateRequest->RequestedShipment->RateRequestTypes = [SimpleType\RateRequestType::_ACCOUNT, SimpleType\RateRequestType::_LIST];
        $rateRequest->RequestedShipment->RateRequestTypes = [SimpleType\RateRequestType::_ACCOUNT, SimpleType\RateRequestType::_LIST];

        $rateRequest->RequestedShipment->PackageCount = $this->getNumberOfPieces();

        //create package line items
        $rateRequest->RequestedShipment->RequestedPackageLineItems = [];
        /** @var $items ItemBag */
        $items = $this->getItems();
        if($items->count()) {
            foreach($items->all() as $item) {
                $piece = new ComplexType\RequestedPackageLineItem();
                $piece->Weight->Value = $convert->convertWeightUnit($item->getWeight(), $this->getWeightUnit());
                $piece->Weight->Units = $convert->validateWeightUnit($this->getWeightUnit()) == MassUnit::KILOGRAM ? SimpleType\WeightUnits::_KG : SimpleType\WeightUnits::_LB;
                $piece->Dimensions->Length = $convert->convertLengthUnit($item->getDepth(), $this->getDimensionUnit());
                $piece->Dimensions->Width = $convert->convertLengthUnit($item->getWidth(), $this->getDimensionUnit());
                $piece->Dimensions->Height = $convert->convertLengthUnit($item->getHeight(), $this->getDimensionUnit());
                $piece->Dimensions->Units = $convert->validateLengthUnit($this->getDimensionUnit()) == LengthUnit::CENTIMETRE ? SimpleType\LinearUnits::_CM : SimpleType\LinearUnits::_IN;
                $piece->GroupPackageCount = $item->getQuantity();
                $rateRequest->RequestedShipment->RequestedPackageLineItems[] = $piece;
            }
        }

        return $rateRequest;
    }

    public function sendData($data) {
        $rateServiceRequest = new Request();
        $rateServiceRequest->getSoapClient()->__setLocation($this->getEndpoint());
        $response = $rateServiceRequest->getGetRatesReply($data);

        return $this->createResponse($response);
    }

    /**
     * @param $data
     * @return ShippingQuoteResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new ShippingQuoteResponse($this, $data);
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