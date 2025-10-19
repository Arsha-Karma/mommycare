<?php
class Payment {
    private $razorpay_key_id;
    private $razorpay_key_secret;
    
    public function __construct($key_id, $key_secret) {
        $this->razorpay_key_id = $key_id;
        $this->razorpay_key_secret = $key_secret;
    }
    
    public function verifyPayment($payment_id) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/payments/" . $payment_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, $this->razorpay_key_id . ":" . $this->razorpay_key_secret);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $payment_data = json_decode($response, true);
                
                if ($payment_data && isset($payment_data['status']) && $payment_data['status'] === 'captured') {
                    return [
                        'success' => true,
                        'payment_data' => $payment_data
                    ];
                }
            }
            
            return ['success' => false, 'message' => 'Payment verification failed'];
            
        } catch (Exception $e) {
            error_log("Payment verification error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getKeyId() {
        return $this->razorpay_key_id;
    }
}