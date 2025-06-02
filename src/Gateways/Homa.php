<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class Homa implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'Homa';
    }

    public static function name() {
        return 'Homais.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $errors = [];

        foreach ( $this->mobile as $mobile ) {

            $remote = wp_remote_get( 'https://api.homais.com/services/messaging/sms/api/sendMessage/direct/?Username=' . $username . '&Password=' . $password . '&PortalCode=' . $from . '&Mobile=' . $mobile . '&Message=' . urlencode( $massage ) . '&ServerType=99' );

            $response = wp_remote_retrieve_body( $remote );

            if ( abs( $response ) < 30 ) {
                $errors[] = $response;
            }
        }

        if ( empty( $errors ) ) {
            return true; // Success
        } else {
            $response = $errors;
        }

        return $response;
    }
}
