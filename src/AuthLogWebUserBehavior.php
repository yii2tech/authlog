<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\authlog;

use yii\base\Behavior;
use yii\web\User;

/**
 * AuthLogWebUserBehavior is a behavior for [[\yii\web\User]], which triggers [[AuthLogIdentityBehavior::logAuth()]]
 * after user login.
 *
 * Application configuration example:
 *
 * ```php
 * return [
 *     'components' => [
 *         'user' => [
 *             'as authLog' => [
 *                 'class' => 'yii2tech\authlog\AuthLogWebUserBehavior'
 *             ],
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ];
 * ```
 *
 * @see AuthLogIdentityBehavior
 * @see \yii\web\User
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class AuthLogWebUserBehavior extends Behavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            User::EVENT_AFTER_LOGIN => 'afterLogin',
        ];
    }

    /**
     * Handles owner 'afterLogin' event, logging the authentication success.
     * @param \yii\web\UserEvent $event event instance.
     */
    public function afterLogin($event)
    {
        /* @var $identity \yii\web\IdentityInterface|AuthLogIdentityBehavior */
        $identity = $event->identity;
        $identity->logAuth([
            'cookieBased' => $event->cookieBased,
            'duration' => $event->duration,
        ]);
    }
}