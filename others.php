<?php

/**
 * 1) Классы не следует помещать все в один файл. Точнее сказать - в одном файле должно быть не более одного класса.
 *      Имена файла и класса должны совпадать. Разумеется - это не требование языка, а рекомендация PSR
 * 2) Если мы реализуем получение значений свойств объекта геттером, то нет смысла делать эти свойства публичными
 * 3) Желательно сопровождать методы PHPDoc-блоками. Я ниже их добавлю
 * 4) Пространство имен и расположение файлов проекта не коррелируется с PSR-4.
 *      Как следствие, автозагрузку если и можно реализовать, то тоже без соотвествия PSR
 */

namespace NW\WebService\References\Operations\Notification;

/**
 * 1) Свойство Seller описано в PHPDoc, но не описано в самом классе. К тому же начинается с заглавной буквы,
 * что противоречит PSR-2. Заглавную букву оставляю, т.к. какой то внешний код может быть уже заточен под это
 * Также свойство $Seller оставляю публичным по тем же причинам
 * 2) Предположим, что используем версию php 7+, или 8+, тогда есть смысл тепизировать свойства при объявлении,
 * что сделает код строже, понятнее и снизит вероятность ошибки при выполнении скрипта
 * 3) Свойства id, $type и $name оставляю так же публичными, для совместимости с вероятным кодом заточенным под
 * такую реализацию.
 * 4) Геттеры не делаю, т.к. свойства публмчные
 * 5) Этот класс напрашивается стать абстрактным, но не делаю его таковым, т.к. возможно существует код, который
 *  создает объекты от этого класса
 *
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;

    public Seller $Seller;
    public int $id;
    public int $type;
    public string $name;


    /**
     * @param array $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = $attributes['id'];
        $this->type = $attributes['type'];
        $this->name = $attributes['name'];
    }


    /**
     * По всей видимости, тут имитация получения экземпляра класса, по его id. Текущий метод лучше сделать статичным.
     * Полагаю, не исключено, что может возвращать null, если экземпляра с таким id не будет найдено
     *
     * @param int $contractorId
     * @return static|null
     *
     * входящий параметр лучше назвать $contractorId.
     * Как выяснилось позже, желательно реализовать класс Client дочерний от текущего. А текущий сделать абстрактным
     */
    public static function getById(int $contractorId): ?self
    {

        /**
         * @var array $attributes
         */

        // Тут предполагается какая то реализация поиска данных по id, харркатерных для экземпляра текущего класса,
        // а также заполнение массива аттрибутов характерных для этого экземпляра. id экземпляра так же поместим
        // в массив атрибутов

        return new static($attributes); // fakes the getById method
    }


    /**
     * 1) Нет единой концепции. Где-то используются геттеры, а где-то свойтва читаются напрямую из оъекта.
     *  Если делаем уклон в сторону целостности объекта, то стоит закрыть свойства видимостью private и использовать
     *  геттеры и сеттеры. Так же, если свойство инициализируется единожды и впоследствии не меняется, то
     *  в случае php 8.1+ можно использовать атрибут readonly, область видимости сделать protected или public и
     *  отказаться от гетеров, для удобства
     *
     *
     * @return string
     */
    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}


class Client extends Contractor
{
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}


/**
 * Тут было бы правильно реализовать этот класс в виде модели данных
 */
class Status
{

    // меняю область видимости, т.к. работаем через геттер
    public $id, $name;


    /**
     * Возвращает статус по его id
     * @param int $id
     * @return Status|null
     */
    public static function getById(int $id)
    {

        $item = self::all();

        if (!isset($item[$id])){

            return null;
        }

        $instance = new self();
        $instance->id = $id;
        $instance->name = $item[$id];

        return $instance;
    }


    /**
     * Возвращает все статусы в виде массива
     * @return string[]
     */
    public static function all()
    {

        return [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];
    }

    /**
     * Учитывая комментарий к классу, геттер надо делать не статичным (переделываю),
     * а обычным методом и применять его от объекта (в контексте this).
     * К тому же тут еще возможна ошибка несуществующего инлекса массива. Никакой валидации не реализовано.
     * При реализации класса, как модель данных, этот вопрос вообще не возникал бы. Объект либо есть, либо его нет
     *
     * @param int $id
     * @return string
     */
    public function getName(): string
    {

        return $this->name;
    }

}


abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    private $request;


    /**
     *  Реализовано для устранения некрасивого проектирования. Например, для оправдания нестатичности метода getRequestParam()
     */
    public function __construct()
    {
        $this->request = $_REQUEST;
    }


    /**
     * Тоже странная реализация. Используем нестатичный геттер для получения сущности, не относящейся к классу.
     *  Меняю архитектуру. Метод оставляю не статичным, но атрибуты будут браться не из $_REQUEST, а из свойства $request,
     *  в которое ранее будетпомещен $_REQUEST
     * @param $pName
     * @return mixed
     */
    public function getRequestParam($pName)
    {

        return $this->request[$pName] ?? null;
    }
}


/**
 * Опять отсутствие единой концепции. Вперемешку ООП и процедурный стили
 *
 * @return string
 */
function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}



class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}