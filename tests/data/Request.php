<?php

namespace yii2tech\tests\unit\authlog\data;

/**
 * Mock for the [[\yii\web\Request]], which covers [[\yii\web\User]] requirements.
 */
class Request extends \yii\console\Request
{
    /**
     * Returns the user IP address.
     * @return string user IP address. Null is returned if the user IP address cannot be detected.
     */
    public function getUserIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }
}