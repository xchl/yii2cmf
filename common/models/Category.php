<?php

namespace common\models;

use backend\behaviors\PositionBehavior;
use common\behaviors\CacheInvalidateBehavior;
use common\behaviors\MetaBehavior;
use common\helpers\Tree;
use common\models\behaviors\CategoryBehavior;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\caching\TagDependency;

/**
 * This is the model class for table "{{%article}}".
 *
 * @property int $id
 * @property int $pid
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property int $created_at
 * @property int $updated_at
 */
class Category extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%category}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'slug'], 'required'],
            ['module', 'string'],
            [['pid', 'sort', 'allow_publish'], 'integer'],
            ['pid', 'default', 'value' => 0],
            [['sort'], 'default', 'value' => 0]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => '分类名',
            'slug' => '标识',
            'pid' => '上级分类',
            'ptitle' => '上级分类', // 非表字段,方便后台显示
            'description' => '分类介绍',
            'article' => '文章数', //冗余字段,方便查询
            'sort' => '排序',
            'module' => '文档类型',
            'allow_publish' => '是否允许发布内容',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
            [
                'class' => MetaBehavior::className(),
                'type' => 'category'
            ],
            CategoryBehavior::className(),
            'positionBehavior' => [
                'class' => PositionBehavior::className(),
                'positionAttribute' => 'sort',
                'groupAttributes' => [
                    'pid'
                ],
            ],
            [
                'class' => CacheInvalidateBehavior::className(),
                'tags' => [
                    'categoryList'
                ]

            ]
        ];
    }

    public function getMetaData()
    {
        $model =  $this->getMetaModel();

        $title = $model->title ? : $this->title;
        $keywords = $model->keywords;
        $description =$model->description ? : $this->description;

        return [$title, $keywords, $description];
    }
    /**
     * 获取分类名
     */
    public function getPtitle()
    {
        return static::find()->select('title')->where(['id' => $this->pid])->scalar();
    }

    public static function lists($module = null)
    {
        $list = Yii::$app->cache->get(['categoryList', $module]);
        if ($list === false) {
            $list = static::find()->filterWhere(['module' => $module])->asArray()->all();
            Yii::$app->cache->set(['categoryList', $module], $list, new TagDependency(['tags' => ['categoryList']]));
        }
        return $list;
    }

    public static function tree($list = null)
    {
        if (is_null($list)) {
            $list = self::find()->asArray()->all();
        }

        $tree = Tree::build($list);
        return $tree;
    }

    public static function treeList($tree = null, &$result = [], $deep = 0, $separator = '--')
    {
        if (is_null($tree)) {
            $tree = self::tree();
        }
        $deep++;
        foreach($tree as $list) {
            $list['title'] = str_repeat($separator, $deep-1) . $list['title'];
            $result[] = $list;
            if (isset($list['children'])) {
                self::treeList($list['children'], $result, $deep, $separator);
            }
        }
        return $result;
    }
    public static function getDropDownList($tree = [], &$result = [], $deep = 0, $separator = '--')
    {
        $deep++;
        foreach($tree as $list) {
            $result[$list['id']] = str_repeat($separator, $deep-1) . $list['title'];
            if (isset($list['children'])) {
                self::getDropDownlist($list['children'], $result, $deep);
            }
        }
        return $result;
    }

    public function getCategoryNameById($id)
    {
        $list = $this->lists();

        return isset($list[$id]) ? $list[$id] : null;
    }

    public static function getIdByName($name)
    {
        $list = self::lists();

        return array_search($name, $list);
    }

    public static function findByIdOrSlug($id)
    {
        if (intval($id) == 0) {
            $condition = ["slug" => $id];
        } else {
            $condition = [
                $id
            ];
        }

        return static::findOne($condition);
    }

    public static function getAllowPublishEnum()
    {
        return [
            '不允许',
            '只允许后台',
            '允许前后台'
        ];
    }
}
