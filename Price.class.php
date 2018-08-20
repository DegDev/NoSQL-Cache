<?php
/**
 * @author Dmitri Puscas <degwelloa@gmail.com> 
 */

namespace App\Price;

use App\Config\Config,
    App\Cache\Cache,
    App\Goods,
    App\Price\PriceFormatter;

/**
 * Класс данных цен.
 * Преднозначен для формирования цен в местных валютах пользователей.
 * Информация о ценах запрашивается через GoodsAPI и кэшируется. 
 *
 * <code>
 * <?php
 * use App\Price;
 *
 *   $country = 'GB';
 *   $price = new Price($country);
 *
 *   $price->applyCoupon('discountCoupon'); 
 *
 *   echo $price->getPrice('boost-speed');
 *   echo $price->getDiscountedPrice('boost-speed');
 *
 *   echo $price->getFormattedPrice('boost-speed');
 * 
 * </code>
 */
class Price {
    /**
     * Страна пользователя
     * @var string
     */
    private $country;
    /**
     * Валюта пользователя
     * @var string
     */
    private $currency = 'USD';
    /**
     * Массив цен
     * @var array
     */
    private $prices   = [];
    /**
     * Объект класса для работы с запросами
     * @var Goods\API
     */
    private $goods;
    /**
     * Массив id программ для запроса в GoodsAPI
     * @var array
     */
    public $goodsIds  = [
        'program-1',
        'program-2',
        'program-3',        
    ];
    public function __construct($country, $coupon = null) {

        $url      = 'http://some.api_url.com/api';
        $user     = 'user.api';
        $password = 'sword-fish';

        $this->goods = new Goods\API($url, $user, $password);

        $this->country = strtolower($country);

        $this->currency = $this->requestCurrency();
        $this->prices   = $this->requestLocalPrices($coupon);
    }
    /**
     * Запросить валюту.<br>
     * Извлекает из кэша код валюты. <br>
     * Кэширует запрос в случае если кэша не существует.<br>
     *
     * @return string Код валюты
     */
    public function requestCurrency() {
        // Если страна не опознана то $country = null;
        if (!empty($this->country)) {
            // забираем массив Стран через кэш
            $countries = Cache::store()->remember('countries', 60 * 24 * 31,
                function() {
                return $this->goods->requestCountries();
            });
            $currency = $countries[$this->country]['currency'];
        } else {
            $currency = 'USD';
        }
        return $currency;
    }
    /**
     * Запросить локальные цены для программ.
     * Купон используется для извелечения цен с применённой скидкой.<br>
     *
     * @param string $coupon  Скидочный Купон
     * @return string Код валюты
     */
    private function requestLocalPrices($coupon) {
        // Формируем ключ кэша
        $key = "{$this->getCurrency()}";
        // Если есть купон то добавляем его к ключу
        if (!empty($coupon)) {
            $key = "{$key}_{$coupon}";
        }
        // Получаем массив данных из кэша, если кэш отсутвует то
        // он будет создан
        $prices = Cache::store('prices')->remember($key, 60 * 24 * 31,
            function() use($coupon) {
            $goodsArray = $this->goods->requestGoodsPrices($this->goodsIds,
                $this->getCurrency(), $this->getCountry(), $coupon);
            // сохранить в кэше отконвертированный результат запроса,
            // и возвратить его значение
            return $this->convert($goodsArray);
        });
        return $prices;
    }
    /**
     * Применить скидочный купон.
     * Перезапрашивает локальные цены для данного купона.
     *
     * @param string $coupon  Скидочный Купон
     */
    public function applyCoupon($coupon) {
        $this->prices = $this->requestLocalPrices($coupon);
    }
    /**
     * Сбросить скидочный купон.
     * Перезапрашивает локальные цены без купона.
     * 
     */
    public function resetCoupon() {
        $this->prices = $this->requestLocalPrices(null);
    }
    /**
     * Получить код страны.
     *
     * @return string Код страны
     */
    public function getCountry() {
        return $this->country;
    }
    /**
     * Получить код валюты.
     *
     * @return string Код валюты
     */
    public function getCurrency() {
        return $this->currency;
    }
    /**
     * Преобразует массив цен полученые из GoodsAPI в массив цен используемый
     * на проекте
     *
     * @param  array $goods массив цен полученный из GoodsAPI
     * @return array $software массив цен используемый на проекте
     */
    private function convert($goods) {
        // В случае изменения ответа GoodsAPI достаточно поменять ключи
        // в массиве $needKeys на изменённые.
        $needKeys = [
            'price'     => 'discounted',
            'basePrice' => 'full',
        ];
        $software = [];
        foreach ($goods as $goodsKey => $goodsValues) {
            $softwareKey            = $this->normalizeId($goodsKey);
            $software[$softwareKey] = $this->filter($goodsValues, $needKeys);
        }
        return $software;
    }
    /**
     * Отбирает из массива goodsValues пары(ключ=>значение) указанные в массиве
     * $needKeys. Ключами результирующего массива будут являться значения
     * массива $needKeys.
     *
     * @param  array $goodsValues массив значений для цен полученный из GoodsAPI
     * @return array $filtered отфильтрованный массив цен используемый на проекте
     */
    private function filter($goodsValues, $needKeys) {
        $filtered = [];
        foreach ($goodsValues as $key => $value) {
            if (array_key_exists($key, $needKeys)) {
                $filtered += [$needKeys[$key] => $value];
            }
        }
        return $filtered;
    }    
    /**
     * Нормализует id продукта, делая из goodsId - softwareId
     *
     * @param  string $goodsId    id полученный из GoodsAPI
     * @return string $softwareId id используемый на проекте
     */
    private function normalizeId($goodsId) {
        // Подстроки goodsId которые необходимо удалить
        $removedSubstrings = [
            'removed-',
        ];
        $softwareId        = '';
        foreach ($removedSubstrings as $substring) {
            $softwareId = str_replace($substring, '', $goodsId);
        }
        return $softwareId;
    }
    /**
     * Получить полную цену.
     *
     * @return mixed(float/int) Цена
     */
    public function getPrice($softwareId) {
        $this->verifyId($softwareId);
        return $this->prices[$softwareId]['full'];
    }
    /**
     * Получить цену со скидкой.
     *
     * @return mixed(float/int) Цена со скидкой
     */
    public function getDiscountedPrice($softwareId) {
        $this->verifyId($softwareId);
        return $this->prices[$softwareId]['discounted'];
    }
    /**
     * Получить сумму скидку.
     *
     * @return mixed(float/int) Сумма скидки
     */
    public function getSavedPrice($softwareId) {
        $this->verifyId($softwareId);
        return $this->prices[$softwareId]['full'] - $this->prices[$softwareId]['discounted'];
    }
    /**
     * Подтверждить что поступивший id является подлинным.
     *
     * @param string $softwareId id софта
     * @throws Exception
     */
    private function verifyId($softwareId) {
        if (!isset($this->prices[$softwareId])) {
            throw new \Exception("Key: '{$softwareId}'  does not exists.");
        }
    }
    /**
     * Получить отформатированную цену. 
     *
     * @return string Цена
     */
    public function getFormattedPrice($softwareId) {
        return PriceFormatter::make($this->getPrice($softwareId),
                $this->getCurrency());
    }
    /**
     * Получить отформатированную цену со скидкой.
     *
     * @return string Отформатированная цена со скидкой
     */
    public function getFormattedDiscountedPrice($softwareId) {
        return PriceFormatter::make($this->getDiscountedPrice($softwareId),
                $this->getCurrency());
    }
    /**
     * Получить отформатированную сумму скидки.
     *
     * @return string Отформатированная сумма скидки
     */
    public function getFormattedSavedPrice($softwareId) {
        return PriceFormatter::make($this->getSavedPrice($softwareId),
                $this->getCurrency());
    }
}