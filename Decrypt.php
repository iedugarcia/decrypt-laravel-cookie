<?php

class Decrypt{
    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;

    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher = MCRYPT_RIJNDAEL_128;

    /**
     * The mode used for encryption.
     *
     * @var string
     */
    protected $mode = MCRYPT_MODE_CBC;

    public function __construct($key)
    {
        $this->key = (string) $key;
    }

    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @return string
     */
    private function decrypt($payload)
    {
        $payload = $this->getJsonPayload($payload);

        // We'll go ahead and remove the PKCS7 padding from the encrypted value before
        // we decrypt it. Once we have the de-padded value, we will grab the vector
        // and decrypt the data, passing back the unserialized from of the value.
        $value = base64_decode($payload['value']);

        $iv = base64_decode($payload['iv']);

        return unserialize($this->stripPadding($this->mcryptDecrypt($value, $iv)));
    }

    /**
     * Remove the padding from the given value.
     *
     * @param  string  $value
     * @return string
     */
    protected function stripPadding($value)
    {
        $pad = ord($value[($len = strlen($value)) - 1]);

        return $this->paddingIsValid($pad, $value) ? substr($value, 0, $len - $pad) : $value;
    }

    /**
     * Run the mcrypt decryption routine for the value.
     *
     * @param  string  $value
     * @param  string  $iv
     * @return string
     *
     * @throws Exception
     */
    protected function mcryptDecrypt($value, $iv)
    {
        return mcrypt_decrypt($this->cipher, $this->key, $value, $this->mode, $iv);
    }

    /**
     * Determine if the given padding for a value is valid.
     *
     * @param  string  $pad
     * @param  string  $value
     * @return bool
     */
    protected function paddingIsValid($pad, $value)
    {
        $beforePad = strlen($value) - $pad;

        return substr($value, $beforePad) == str_repeat(substr($value, -1), $pad);
    }

    /**
     * Get the JSON array from the given payload.
     *
     * @param  string  $payload
     * @return array
     *
     * @throws Exception
     */
    protected function getJsonPayload($payload)
    {
        $payload = json_decode(base64_decode($payload), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if ( ! $payload || $this->invalidPayload($payload))
        {
            throw new Exception('Invalid data.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     *
     * @param  array|mixed  $data
     * @return bool
     */
    protected function invalidPayload($data)
    {
        return ! is_array($data) || ! isset($data['iv']) || ! isset($data['value']) || ! isset($data['mac']);
    }

    /**
     * Decrypt cookie hash.
     *
     * @param $hash
     *
     * @return mixed
     */
    public function run($hash){
        $hashUrlDecoded = urldecode($hash);

        return $this->decrypt($hashUrlDecoded);
    }

    public function getUserId($hash){
        $decoded = $this->run($hash);

        list($userId,$token) = explode('|',$decoded);

        return $userId;
    }
}