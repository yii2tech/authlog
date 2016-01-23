<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\authlog;

use yii\base\Behavior;
use yii\db\BaseActiveRecord;

/**
 * AuthLogIdentityBehavior is a behavior for the [[BaseActiveRecord]] model, which implements [[\yii\web\IdentityInterface]] interface.
 * This behavior allows logging of the authentication attempts made via owner identity. It works through the relation
 * to the log entity specified via [[authLogRelation]]. The database migration for such entity creation can be following:
 *
 * ```php
 * $this->createTable('UserAuthLog', [
 *     'id' => $this->primaryKey(),
 *     'userId' => $this->integer(),
 *     'date' => $this->integer(),
 *     'cookieBased' => $this->boolean(),
 *     'duration' => $this->integer(),
 *     'error' => $this->string(),
 *     'ip' => $this->string(),
 *     'host' => $this->string(),
 *     'url' => $this->string(),
 *     'userAgent' => $this->string(),
 * ]);
 * ```
 *
 * Configuration example:
 *
 * ```php
 * class User extends ActiveRecord implements IdentityInterface
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'authLog' => [
 *                 'class' => AuthLogIdentityBehavior::className(),
 *                 'authLogRelation' => 'authLogs',
 *                 'defaultAuthLogData' => function ($model) {
 *                     return [
 *                         'ip' => $_SERVER['REMOTE_ADDR'],
 *                         'host' => @gethostbyaddr($_SERVER['REMOTE_ADDR']),
 *                         'url' => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
 *                         'userAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
 *                     ];
 *                 },
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * @property BaseActiveRecord|\yii\web\IdentityInterface $owner
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class AuthLogIdentityBehavior extends Behavior
{
    /**
     * @var string name of the 'has-many' relation with auth log entity.
     */
    public $authLogRelation = 'authLogs';
    /**
     * @var string name of the auth log model attribute, which should store the log date or timestamp.
     */
    public $dateAttribute = 'date';
    /**
     * @var mixed|callable the default value for [[dateAttribute]].This can be an arbitrary value or PHP
     * callback of the following signature: `mixed function (BaseActiveRecord $model) {}`
     * If not set, current timestamp will be used.
     */
    public $dateDefaultValue;
    /**
     * @var string name of the auth log model attribute, which should store the auth error token.
     */
    public $errorAttribute = 'error';
    /**
     * @var mixed the default value for [[errorAttribute]]. By default `null` will be used.
     */
    public $errorDefaultValue;
    /**
     * @var array|callable|null default data, which should be saved to auth log.
     * This could be an array of [[authLogRelation]] model attribute values or a PHP callback of following
     * signature: `array function (BaseActiveRecord $model) {}`
     */
    public $defaultAuthLogData;
    /**
     * @var integer the probability (parts per million) that garbage collection (GC) should be performed
     * when writing auth log. Defaults to 10000, meaning 1% chance.
     * This number should be between 0 and 1000000. A value 0 means no GC will be performed at all.
     */
    public $gcProbability = 10000;
    /**
     * @var integer number of the last auth logs, which should be kept after garbage collection (GC) performed.
     */
    public $gcLimit = 100;

    /**
     * @var BaseActiveRecord|null
     */
    private $_lastSuccessfulAuthLog = false;
    /**
     * @var BaseActiveRecord|null
     */
    private $_preLastSuccessfulAuthLog = false;


    /**
     * Writes data into the log.
     * @param array $data data to be logged.
     * @return boolean success.
     */
    public function logAuth(array $data = [])
    {
        $authLogModel = $this->newAuthLogModel();
        $data = array_merge(
            $this->composeDefaultAuthLogData(),
            $data
        );
        foreach ($data as $attribute => $value) {
            if ($authLogModel->hasAttribute($attribute) || $authLogModel->canSetProperty($attribute)) {
                $authLogModel->{$attribute} = $value;
            }
        }

        return $authLogModel->save(false);
    }

    /**
     * Writes error info into the log.
     * @param mixed $error error token
     * @param array $data extra data to be logged.
     * @return boolean success.
     */
    public function logAuthError($error, array $data = [])
    {
        $data[$this->errorAttribute] = $error;
        return $this->logAuth($data);
    }

    /**
     * Returns default auth log data.
     * @return array default log data.
     */
    protected function composeDefaultAuthLogData()
    {
        if ($this->dateDefaultValue === null) {
            $date = time();
        } else {
            if (is_callable($this->dateDefaultValue)) {
                $date = call_user_func($this->dateDefaultValue, $this->owner);
            } else {
                $date = $this->dateDefaultValue;
            }
        }

        $data = [
            $this->errorAttribute => $this->errorDefaultValue,
            $this->dateAttribute => $date,
        ];

        if ($this->defaultAuthLogData !== null) {
            if (is_callable($this->defaultAuthLogData)) {
                $extraData = call_user_func($this->defaultAuthLogData, $this->owner);
            } else {
                $extraData = $this->defaultAuthLogData;
            }
            $data = array_merge($data, $extraData);
        }

        return $data;
    }

    /**
     * Returns the instance of the [[authLogRelation]] relation.
     * @return \yii\db\ActiveQueryInterface|\yii\db\ActiveRelationTrait variations relation.
     */
    private function getAuthLogRelation()
    {
        return $this->owner->getRelation($this->authLogRelation);
    }

    /**
     * Creates new instance of the auth log model.
     * @return BaseActiveRecord auth log model instance.
     */
    protected function newAuthLogModel()
    {
        $relation = $this->getAuthLogRelation();
        $modelClass = $relation->modelClass;
        list($ownerReferenceAttribute) = array_keys($relation->link);
        $model = new $modelClass();
        $model->{$ownerReferenceAttribute} = $this->owner->getPrimaryKey();
        return $model;
    }

    // Statistics :

    /**
     * Finds successful auth log record.
     * @param integer $offset offset to be applied
     * @return BaseActiveRecord|null model instance.
     */
    protected function findLastSuccessfulAuthLog($offset)
    {
        return $this->getAuthLogRelation()
            ->andWhere([$this->errorAttribute => $this->errorDefaultValue])
            ->orderBy([$this->dateAttribute => SORT_DESC])
            ->offset($offset)
            ->limit(1)
            ->one();
    }

    /**
     * @param boolean $refresh whether to refresh internal cache.
     * @return BaseActiveRecord|null auth log model.
     */
    public function getLastSuccessfulAuthLog($refresh = false)
    {
        if ($refresh || $this->_lastSuccessfulAuthLog === false) {
            $this->_lastSuccessfulAuthLog = $this->findLastSuccessfulAuthLog(0);
        }
        return $this->_lastSuccessfulAuthLog;
    }

    /**
     * @param boolean $refresh whether to refresh internal cache.
     * @return BaseActiveRecord|null auth log model.
     */
    public function getPreLastSuccessfulAuthLog($refresh = false)
    {
        if ($refresh || $this->_preLastSuccessfulAuthLog === false) {
            $this->_preLastSuccessfulAuthLog = $this->findLastSuccessfulAuthLog(1);
        }
        return $this->_preLastSuccessfulAuthLog;
    }

    /**
     * @param boolean $refresh whether to refresh internal cache.
     * @return mixed|null last successful login date.
     */
    public function getLastLoginDate($refresh = false)
    {
        if (($authLogModel = $this->getLastSuccessfulAuthLog($refresh)) !== null) {
            return $authLogModel->{$this->dateAttribute};
        }
        return null;
    }

    /**
     * @param boolean $refresh whether to refresh internal cache.
     * @return mixed|null pre-last successful login date.
     */
    public function getPreLastLoginDate($refresh = false)
    {
        if (($authLogModel = $this->getPreLastSuccessfulAuthLog($refresh)) !== null) {
            return $authLogModel->{$this->dateAttribute};
        }
        return null;
    }

    /**
     * Check if last auth logs compose a sequence of failed login attempt with given length.
     * @param integer $length sequence length.
     * @return boolean whether there is requested failure sequence or not.
     */
    public function hasFailedLoginSequence($length)
    {
        $authLogModels = $this->getAuthLogRelation()
            ->orderBy([$this->dateAttribute => SORT_DESC])
            ->limit($length)
            ->all();

        if (count($authLogModels) < $length) {
            return false;
        }

        foreach ($authLogModels as $authLogModel) {
            if ($authLogModel->{$this->errorAttribute} === $this->errorDefaultValue) {
                return false;
            }
        }

        return true;
    }

    // Garbage collection

    /**
     * Removes auth logs, which overflow [[gcLimit]].
     * @param boolean $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     */
    public function gcAuthLogs($force = false)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $relation = $this->getAuthLogRelation();
            $borderRecord = $relation
                ->orderBy([$this->dateAttribute => SORT_DESC])
                ->offset($this->gcLimit)
                ->limit(1)
                ->one();
            if ($borderRecord === null) {
                return;
            }

            /* @var $modelClass BaseActiveRecord */
            $modelClass = $relation->modelClass;
            list($ownerReferenceAttribute) = array_keys($relation->link);

            $modelClass::deleteAll([
                'and',
                [
                    $ownerReferenceAttribute => $this->owner->getPrimaryKey()
                ],
                [
                    '<=', $this->dateAttribute, $borderRecord->{$this->dateAttribute}
                ],
            ]);
        }
    }
}