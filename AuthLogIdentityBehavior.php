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
 * AuthLogIdentityBehavior
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
    public function writeAuthLog(array $data = [])
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
    public function writeAuthLogError($error, array $data = [])
    {
        $data[$this->errorAttribute] = $error;
        return $this->writeAuthLog($data);
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
}