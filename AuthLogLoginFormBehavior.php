<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\authlog;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\validators\Validator;
use yii\web\IdentityInterface;

/**
 * AuthLogLoginFormBehavior is a behavior for the login form model, which allows "brute-force" attack protection.
 * This behavior requires [[AuthLogIdentityBehavior]] being attached to the related identity class as well as
 * [[AuthLogWebUserBehavior]] behavior being attached to the application 'user' component.
 *
 * 2 level of protection are provided:
 *  - require robot verification (CAPTCHA) after [[verifyRobotFailedLoginSequence]] sequence login failures
 *  - deactivation of the identity record after [[deactivateFailedLoginSequence]] sequence login failures
 *
 * Example:
 *
 * ```php
 * class LoginForm extends Model
 * {
 *     public $username;
 *     public $password;
 *     public $rememberMe = true;
 *     public $verifyCode;
 *
 *     public function behaviors()
 *     {
 *         return [
 *             'authLog' => [
 *                 'class' => AuthLogLoginFormBehavior::className(),
 *                 'deactivateIdentity' => 'suspend'
 *             ],
 *         ];
 *     }
 *     // ...
 * }
 * ```
 *
 * @property Model $owner
 * @property IdentityInterface|AuthLogIdentityBehavior|null $identity related identity model.
 * @property boolean $isVerifyRobotRequired whether the robot verification required or not.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class AuthLogLoginFormBehavior extends Behavior
{
    /**
     * @var string|callable name of the owner method or a PHP callback, which should return the current identity instance.
     * If string - considered as name of the [[owner]] method, otherwise as independent PHP callback.
     * If not set - internal [[findIdentity()]] method will be used.
     * Signature of the callback:
     *
     * ```php
     * IdentityInterface function() {...}
     * ```
     */
    public $findIdentity;
    /**
     * @var string|callable name of the identity class method or a PHP callback, which should be invoked for identity deactivation.
     * If string - considered as name of the identity class method, otherwise as independent PHP callback of the following
     * signature:
     *
     * ```php
     * function(IdentityInterface $identity) {...}
     * ```
     *
     * If not set no deactivation will be ever performed.
     */
    public $deactivateIdentity;
    /**
     * @var array list of the owner attributes, which are used to validate identity authentication.
     */
    public $verifyIdentityAttributes = ['password'];
    /**
     * @var array list of error tokens, which should be used to log authentication failure on error at [[verifyIdentityAttributes]]
     * in format: attributeName => errorToken. For example:
     *
     * ```php
     * [
     *     'password' => 'Invalid Password'
     * ]
     * ```
     *
     * If no error token is explicitly specified, attribute name will be used instead.
     */
    public $verifyIdentityAttributeErrors = [];
    /**
     * @var string name of the owner attribute, which should be used for CAPTCHA verification code entry.
     * If not set, no robot verification will be ever performed.
     */
    public $verifyRobotAttribute;
    /**
     * @var array validation rule, which should be used for robot verification.
     * Note: in addition to this, owner model should provide a validation rule, which makes [[verifyRobotAttribute]] 'safe'!
     */
    public $verifyRobotRule = ['captcha'];
    /**
     * @var integer|boolean length of failed login attempts sequence, which should trigger robot verification.
     * If set to `false` - no robot check will be ever performed.
     */
    public $verifyRobotFailedLoginSequence = 3;
    /**
     * @var integer|boolean length of failed login attempts sequence, which should trigger identity deactivation.
     * If set to `false` - no identity deactivation will be ever performed.
     */
    public $deactivateFailedLoginSequence = 10;

    /**
     * @var IdentityInterface|AuthLogIdentityBehavior|null|boolean
     */
    private $_identity = false;
    /**
     * @var boolean
     */
    private $_isVerifyRobotRequired;


    /**
     * @return IdentityInterface|null
     */
    public function setIdentity($identity)
    {
        $this->_identity = $identity;
    }

    /**
     * @return IdentityInterface|AuthLogIdentityBehavior|null
     */
    public function getIdentity()
    {
        if ($this->_identity === false) {
            if ($this->findIdentity === null) {
                $this->_identity = $this->findIdentity();
            } else {
                if (is_string($this->findIdentity)) {
                    $this->_identity = call_user_func([$this->owner, $this->findIdentity]);
                } else {
                    $this->_identity = call_user_func($this->findIdentity);
                }
            }
        }
        return $this->_identity;
    }

    /**
     * Finds current identity assuming there is [[findByLoginName()]] identity method.
     * @return IdentityInterface|null identity instance, `null` if not found.
     */
    protected function findIdentity()
    {
        $loginNameAttribute = 'username';
        $loginNameValue = trim($this->owner->{$loginNameAttribute});
        if (empty($loginNameValue)) {
            return null;
        }
        $identityClass = Yii::$app->user->identityClass;
        return $identityClass::findByLoginName($loginNameValue);
    }

    /**
     * @return boolean
     */
    public function getIsVerifyRobotRequired()
    {
        if ($this->_isVerifyRobotRequired === null) {
            $this->_isVerifyRobotRequired = $this->findIsRobotCheckRequired();
        }
        return $this->_isVerifyRobotRequired;
    }

    /**
     * @param boolean $isRobotCheckRequired
     */
    public function setIsVerifyRobotRequired($isRobotCheckRequired)
    {
        $this->_isVerifyRobotRequired = $isRobotCheckRequired;
    }

    /**
     * @return boolean
     */
    protected function findIsRobotCheckRequired()
    {
        if ($this->verifyRobotFailedLoginSequence === false || $this->verifyRobotAttribute === null) {
            return false;
        }
        $identity = $this->getIdentity();
        if ($identity !== null) {
            return $identity->hasFailedLoginSequence($this->verifyRobotFailedLoginSequence);
        }
        return false;
    }

    /**
     * Performs verification against robots using [[verifyRobotRule]] validation rule.
     */
    protected function verifyRobot()
    {
        $rule = $this->verifyRobotRule;
        if ($rule instanceof Validator) {
            $validator = $rule;
        } elseif (is_array($rule) && isset($rule[0])) { // validator type
            $validator = Validator::createValidator($rule[0], $this->owner, (array) $this->verifyRobotAttribute, array_slice($rule, 1));
        } else {
            throw new InvalidConfigException('Invalid validation rule: a rule must specify validator type.');
        }

        $validator->validateAttribute($this->owner, $this->verifyRobotAttribute);
    }

    /**
     * Writes error info into the log.
     * @param mixed $error error token.
     * @param array $data extra data to be logged.
     */
    public function logAuthError($error, array $data = [])
    {
        $identity = $this->getIdentity();
        if ($identity !== null) {
            $identity->logAuthError($error, $data);
        }
    }

    /**
     * Deactivates current identity.
     * @return boolean|mixed deactivation result.
     */
    public function deactivateIdentity()
    {
        $identity = $this->getIdentity();
        if (!is_object($identity)) {
            return false;
        }

        if ($this->deactivateIdentity === null) {
            return false;
        } elseif (is_string($this->deactivateIdentity)) {
            return call_user_func([$identity, $this->deactivateIdentity]);
        } else {
            return call_user_func($this->deactivateIdentity, $identity);
        }
    }

    // Events :

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Model::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            Model::EVENT_AFTER_VALIDATE => 'afterValidate',
        ];
    }

    /**
     * Handles owner 'beforeValidate' event.
     * @param \yii\base\ModelEvent $event event instance.
     */
    public function beforeValidate($event)
    {
        if ($this->getIsVerifyRobotRequired()) {
            $this->verifyRobot();
        }
    }

    /**
     * Handles owner 'afterValidate' event.
     * @param \yii\base\Event $event event instance.
     */
    public function afterValidate($event)
    {
        $identity = $this->getIdentity();
        if (!is_object($identity)) {
            return;
        }

        foreach ($this->verifyIdentityAttributes as $attribute) {
            if ($this->owner->hasErrors($attribute) && !empty($this->owner->{$attribute})) {
                $errorToken = isset($this->verifyIdentityAttributeErrors[$attribute]) ? $this->verifyIdentityAttributeErrors[$attribute] : $attribute;
                $this->logAuthError($errorToken);

                if ($this->deactivateFailedLoginSequence !== false && $this->deactivateIdentity !== null) {
                    if ($identity->hasFailedLoginSequence($this->deactivateFailedLoginSequence)) {
                        $this->deactivateIdentity();
                    }
                }

                break;
            }
        }
    }
}