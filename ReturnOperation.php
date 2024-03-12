<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;


    private array $data;
    private Seller $reseller;
    private Client $client;
    private int $notificationType;


    /**
     * @return void
     * @throws \Exception
     */
    public function validateRequestAndFillProperties()
    {
        $data = $this->getRequestParam('data');

        // Этой проверки не было, но она не помешает
        if (!isset($data)){
            throw new \Exception('Empty data');
        }

        $this->data = (array) $data;

        if (!isset($this->data['resellerId'])) {
            throw new \Exception('Empty resellerId', 400);
        }

        $this->reseller = Seller::getById($this->data['resellerId']);

        if (!isset($this->reseller)){
            throw new \Exception('Seller not found!', 400);
        }

        if (!isset($this->data['notificationType'])) {
            throw new \Exception('Empty notificationType', 400);
        }

        $this->notificationType = (int)$this->data['notificationType'];

    }


    /**
     * Учитывая, что метод не статичны, подразумеваю, что гдето снаружи будет создаваться экземпляр этого класса
     * @return array
     * @throws \Exception
     */
    public function doOperation(): array
    {

        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];


        // Странно, что эта проверка кладет сообщение в массив возвращаемого значения,
        // а остальные проверки генерят исключение. Как то концепция не единая. Но учитывая, что вызывающий
        // код может быть заточен именно под такое поведение, я такое поведение и оаставлю. Хотя,
        // красивее было бы сделать однотипно и реализовать эту проверку вместе с остальными
        // проверками в validateContextAndFillProperties()
        // Ну и раз проверять, то $data['resellerId'], а не $resellerId
        if (empty($data['resellerId'])) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        $this->validateRequestAndFillProperties();


        // Тут бы желательно реализовать класс Client, унаследованный от класса Contractor
        // а класс Contractor сделать абстрактным. А метод getById() в следующей строке брать от класса Client
        $client = Client::getById((int)$data['clientId']);
        // $client === null || можно убрать из условия ниже, т.к. этот фрагмент будет всегда false
        if ($client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $this->reseller->id) {
            throw new \Exception('client not found!', 400);
        }

        $cFullName = $client->getFullName();
        // в условии ниже есть смысл проверять не результат метода, а переменную $cFullName
        // и похоже, это избыточная проверка, т.к. при верной реализации класса Contractor, в полное имя попадет,
        // как минимум, id
        if (empty($client->getFullName())) {
            $cFullName = $client->name;
        }

        // ниже не информативное имя переменной. Перименовываю
        $creator = Employee::getById((int)$data['creatorId']);
        // Условие ниже избыточно, т.к. всегда будет false
        if ($creator === null) {
            throw new \Exception('Creator not found!', 400);
        }

        // ниже не информативное имя переменной. Перименовываю
        $expert = Employee::getById((int)$data['expertId']);
        // Условие ниже избыточно, т.к. всегда будет false
        if ($expert === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            // ниже используется неопределенная функция (предположим, что потом будет какая то реализация).
            // И не рекомендуется пользовательские функции так называть,
            // т.к. такой префикс используется для магических методов
            $differences = __('NewPositionAdded', null, $this->reseller->id);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $statusFrom = Status::getById((int)$data['differences']['from']); // Не делаю валидацию на на наличие объекта, т.к. допускаю, что она предполагается в реализации функции __
            $statusTo = Status::getById((int)$data['differences']['to']);
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => $statusFrom->getName(),
                    'TO'   => $statusTo->getName(),
                ], $this->reseller->id);
        }

        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $creator->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $expert->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($this->reseller->id);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($this->reseller->id, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                // Отсутствует реализация класса MessagesClient
                // Ну и для соблюдения приципа Единой отвественности, есть смысл отправку
                // сообщения реализовывать не в классе самогО сообщения, а в отдельном транспортном классе.
                // Сообщение можно реализовать, как модель, а Транспортный класс, как сервис
                // Не естественно выглядит, когда сообщение отправляет само себя
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $this->reseller->id),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $this->reseller->id),
                    ],
                ], $this->reseller->id, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $client->email,
                           'subject'   => __('complaintClientEmailSubject', $templateData, $this->reseller->id),
                           'message'   => __('complaintClientEmailBody', $templateData, $this->reseller->id),
                    ],
                ], $this->reseller->id, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                // строка ниже слишком длинная. Есть смысл ее отформатировать
                // Используется переменная $error, кторая нигде не определена
                // Отсутствует реализация класса NotificationManager
                $res = NotificationManager::send($this->reseller->id, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
