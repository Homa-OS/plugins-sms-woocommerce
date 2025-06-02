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
            return [
                'status' => 'error',
                'message' => 'نام کاربری یا رمز عبور خالی است',
                'details' => [
                    'username_provided' => !empty($username),
                    'password_provided' => !empty($password),
                    'sender_number' => $from
                ]
            ];
        }

        $errors = [];
        $successes = [];
        $responses = [];

        foreach ( $this->mobile as $mobile ) {
            
            $api_url = 'https://api.homais.com/services/messaging/sms/api/sendMessage/direct/?Username=' . 
                       urlencode($username) . '&Password=' . urlencode($password) . '&PortalCode=' . 
                       urlencode($from) . '&Mobile=' . urlencode($mobile) . '&Message=' . 
                       urlencode( $massage ) . '&ServerType=99';

            $remote = wp_remote_get( $api_url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'WordPress-PWSMS-Plugin/1.0'
                ]
            ] );

            if ( is_wp_error( $remote ) ) {
                $error_message = $remote->get_error_message();
                $errors[] = [
                    'mobile' => $mobile,
                    'error' => 'خطای اتصال: ' . $error_message,
                    'type' => 'connection_error'
                ];
                continue;
            }

            $response_code = wp_remote_retrieve_response_code( $remote );
            $response_body = wp_remote_retrieve_body( $remote );

            $responses[] = [
                'mobile' => $mobile,
                'http_code' => $response_code,
                'response' => $response_body,
                'api_url' => $api_url // For debugging purposes
            ];

            if ( $response_code !== 200 ) {
                $errors[] = [
                    'mobile' => $mobile,
                    'error' => "HTTP Error {$response_code}: {$response_body}",
                    'type' => 'http_error'
                ];
                continue;
            }

            // Check if response is numeric and positive (success condition for Homa API)
            if ( is_numeric( $response_body ) && absint( $response_body ) >= 30 ) {
                $successes[] = [
                    'mobile' => $mobile,
                    'response' => $response_body,
                    'message' => 'ارسال موفق'
                ];
            } else {
                $errors[] = [
                    'mobile' => $mobile,
                    'error' => $this->get_error_message( $response_body ),
                    'response_code' => $response_body,
                    'type' => 'api_error'
                ];
            }
        }

        // If all messages were successful
        if ( empty( $errors ) && !empty( $successes ) ) {
            return true; // Success
        }

        // If there were any errors, return detailed error information
        if ( !empty( $errors ) ) {
            return [
                'status' => 'error',
                'message' => 'برخی یا همه پیامک‌ها ارسال نشدند',
                'errors' => $errors,
                'successes' => $successes,
                'summary' => [
                    'total_mobiles' => count( $this->mobile ),
                    'successful_sends' => count( $successes ),
                    'failed_sends' => count( $errors ),
                    'api_responses' => $responses
                ],
                'gateway' => [
                    'name' => self::name(),
                    'id' => self::id(),
                    'username' => substr($username, 0, 3) . '***',
                    'sender_number' => $from
                ]
            ];
        }

        return [
            'status' => 'error',
            'message' => 'هیچ پیامکی ارسال نشد',
            'details' => 'مشکل نامشخص در ارسال'
        ];
    }

    /**
     * Get human-readable error message based on Homa API response codes
     */
    private function get_error_message( $response_code ) {
        $error_codes = [
            '0' => 'خطای عمومی',
            '1' => 'نام کاربری یا رمز عبور اشتباه',
            '2' => 'اعتبار کافی نیست',
            '3' => 'شماره فرستنده معتبر نیست',
            '4' => 'شماره گیرنده معتبر نیست',
            '5' => 'متن پیامک خالی است',
            '6' => 'متن پیامک طولانی است',
            '7' => 'کد ملی نامعتبر',
            '8' => 'کاربر غیرفعال',
            '9' => 'ارسال از این IP مجاز نیست',
            '10' => 'دامنه غیرمجاز',
            '11' => 'تاریخ انقضای حساب',
            '12' => 'ارسال در این ساعت مجاز نیست',
            '13' => 'محدودیت ارسال روزانه',
            '14' => 'شماره فرستنده غیرفعال',
            '15' => 'متن حاوی کلمات فیلتر شده',
            '16' => 'کاربر مسدود شده',
            '-1' => 'خطای سرور',
            '-2' => 'درخواست نامعتبر',
            '-3' => 'متد HTTP نامعتبر'
        ];

        return $error_codes[ $response_code ] ?? "خطای نامشخص (کد: {$response_code})";
    }
}
