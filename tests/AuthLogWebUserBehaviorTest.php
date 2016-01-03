<?php

namespace yii2tech\tests\unit\authlog;

use yii2tech\authlog\AuthLogWebUserBehavior;
use yii2tech\tests\unit\authlog\data\User;

class AuthLogWebUserBehaviorTest extends TestCase
{
    /**
     * @return \yii\web\User web user component instance.
     */
    protected function createWebUser()
    {
        $webUser = new \yii\web\User([
            'enableSession' => false,
            'identityClass' => User::className(),
        ]);
        $webUser->attachBehavior('authLog', new AuthLogWebUserBehavior());
        return $webUser;
    }

    // Tests :

    public function testWriteAuthLogOnLogin()
    {
        /* @var $identity User */
        $webUser = $this->createWebUser();

        $identity = User::find()->one();

        $webUser->login($identity);

        $this->assertNotEmpty($identity->authLogs);
    }
}