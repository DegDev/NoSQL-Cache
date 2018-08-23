<?php
/**
 *  @author Dmitri Puscas <degwelloa@gmail.com>
 */

namespace App\Cache;

/**
 * Класс кэшированния данных.
 * Предназначен для хранения результатов "дорогих" операций в json файлах
 * на сервере
 * 
 * <code>
 * <?php
 * use App\Cache; 
 *   
 *   $cache = new Cache();
 *
 *   $cache->put('some_key',$value,$minutes);
 * 
 *   $result = $cache->get('some_key')
 * 
 *   print_r($result);
 * 
 *   $cache->forget('some_key');
 * 
 *   $cache->store('example')->put('another_key',$value,$minutes);
 *
 *   print_r(Cache::store('example')->get('another_key'));
 *
 *
 * 
 * </code>
 */
class Cache
{
    /**
     * Базовая директория для хранения кэша
     * @var string
     */
    private static $baseDir = './cache';

    /**
     * Рабочая директория для хранения кэша
     * @var string
     */
    private $dir;

    // Конструктор будет являтся точкой входа не зависимо от способа создания
    // обьекта
    public function __construct($dir = null)
    {
        // Необходимо чтоб весь кэш хранился в одном месте.
        if (empty($dir)) {
            $this->dir = self::$baseDir;
        } else {
            // Удаляем слеши из конца и начала
            // далаем возможным запись
            // $cache = new Cache('/goods/programms/');
            $this->dir = trim(self::$baseDir.'/'.$dir, '/');
        }
    }

    /**
     * Альтернативный способ создания объекта.<br>
     * Задаёт рабочую директорию(хранилище кэша),<br>
     * допускается вложенность:<br>
     *
     *                      Cache::store('prices/programms')->get('key');
     *
     * допускается переключение директории для не статического объекта:<br>
     *                      <code>
     *                      $cache = new Cache();<br>
     *                      // Получить кэш из поддиректории /cache/price <br>
     *                      $cache->store('price')->get('boot-speed_USD); <br>
     *                      // Получить кэш из базовой директории /cache  <br>
     *                      $cache->store()->get('boot-speed_USD);<br>
     *                      </code>
     *
     * @param string $directory ХРАНИЛИЩЕ - дирректория для работы с кэшем.
     * @return <b>Cache</b> Возвращает объект класса Cache
     */
    public static function store($directory = null)
    {

        return new Cache($directory);
    }

    /**
     * Добавить/обновить значение кэша
     *
     * @param string $key     Ключ(кэш-файл) в который мы добавляем/обновляем
     * @param array  $value   Значение которое мы записываем
     * @param int    $minutes  Время на которое требуется сохранить кэш в минутах
     * @throws Exception
     * @return bool <b>TRUE</b> если добавление в кэш прошло успешно <b>FALSE</b> в обратном случае
     */
    public function put($key, array $value, $minutes)
    {
        //Если по любым причинам ключ пуст.
        if (empty($key)) {
            return false;
        }
        // Если директория для записи не существует то создать её
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0766, true);
        }
        // Добавить время жизни кэша в конец файла кэша
        $value = array_merge($value, ['expired' => $minutes * 60]);
        $file  = file_put_contents("{$this->dir}/{$key}.json",
            json_encode($value, JSON_NUMERIC_CHECK));
        // Исключительная ошибка записи файла кэша
        if ($file === false) {
            throw new \Exception("Error writing cache file: {$this->dir}/{$key}.json");
        }
        return true;
    }

    /**
     * Получить значение из кэша
     * 
     * @param string $key     Ключ(кэш-файл) из которого мы извлекаем значение
     * @return array  Ассоциативный Массив<br>
     *                <b>null</b> если кэш просрочен или не существует
     */
    public function get($key)
    {
        // Если такой ключ не существует возвращем null
        if (!$this->has($key)) {
            return null;
        }
        return $this->parseJSON("{$this->dir}/{$key}.json");
    }

    /**
     * Проверяет существует ли файл ключа и не истёк ли срок хранения
     *
     * @param string $key имя файла(ключа) кэша
     * @return bool <b>TRUE</b> если ключ существует <b>FALSE</b> в обратном случае
     */
    public function has($key)
    {
        // Кэш существует тогда и только тогда когда файл кэша существует и срок
        // жизни кэша не истёк.        
        if ($this->isExists($key) && !$this->isExpired($key)) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет ключ, если он есть то возвращает его. <br>
     * Иначе создаёт новый ключ по значению которое вернётся<br>
     * из замыкания и затем возвращает его.
     *
     * @param  string  $key имя файла(ключа) кэша
     * @param  int     $minutes Время хранения кэша
     * @param  Closure $function  Функция ОТСРОЧЕННОГО вызова
     * @return array   Ассоциативный Массив
     */
    public function remember($key, $minutes, \Closure $function)
    {
        if (!$this->has($key)) {
            $this->put($key, $function(), $minutes);
        }
        return $this->get($key);
    }

    /**
     * Удаляет кэш файл не зависимо от срока хранения
     *
     * @param string $key имя файла(ключа) кэша     
     *
     * @return bool <b>TRUE</b> в случае успеха <b>FALSE</b> в обратном случае
     */
    public function forget($key)
    {
        if (!$this->isExists($key)) {
            return false;
        }
        return unlink("{$this->dir}/{$key}.json");
    }

    /**
     * Возвращает значение кэша и сразу удаляет файл кэша
     *
     * @param string $key  Ключ(кэш-файл) из которого мы извлекаем значение
     *
     * @return bool <b>TRUE</b> если ключ существует <b>FALSE</b> в обратном случае
     */
    public function pull($key)
    {
        // если ключа нет то вернём null        
        if (!$this->has($key)) {
            return null;
        }
        // сохраняем значение кэша во временную переменную
        $tmp = $this->get($key);
        // "Забываем ключ", если произошла ошибка то Exception        
        if ($this->forget($key)) {
            return $tmp;
        }
        return false;
    }

    /**
     * Добавлять кэш только если его не существует.
     *
     * @param string $key     Ключ(кэш-файл) в который мы добавляем/обновляем
     * @param array  $value   Значение которое мы записываем
     * @param  int   $minutes Количество минут
     * @return bool <b>TRUE</b> если ключ был добавлен <b>FALSE</b> если такой ключ уже существует
     */
    public function add($key, $value, $minutes)
    {
        // Если ключ существовал
        if ($this->has($key)) {
            return false;
        }
        return $this->put($key, $value, $minutes);
    }

    /**
     * Удаляет весь кэш из папки.
     *
     * @throws Exception
     * @return bool <b>TRUE</b> если все файлы и вложенные папки были удалены 
     */
    public function flush()
    {
        // НЕ допускается удаление корневой директории проекта.
        if ($this->dir == './') {
            throw new \Exception('Project Root directory can not be deleted');
        }

        return $this->deleteDir($this->dir);
    }

    /**
     * Проверяет истёк ли срок действия кэша
     *
     * @param string $key  Ключ(кэш-файл)
     * @return bool <b>TRUE</b> если срок действия кэша истёк <b>FALSE</b> в обратном случае    
     */
    // Баг: невозможно ... вызывать get($key) в expired
    private function isExpired($key)
    {

        $tmp         = $this->parseJSON("{$this->dir}/{$key}.json");
        // getJson //
        $expiredTime = filemtime("{$this->dir}/{$key}.json") + array_pop($tmp);
        if ($expiredTime - time() <= 0) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет существует ли файл кэша
     *
     * @param string $key  Ключ(кэш-файл)
     * @return bool <b>TRUE</b> если файл кэша существует <b>FALSE</b> в обратном случае
     */
    private function isExists($key)
    {
        if (!file_exists("{$this->dir}/{$key}.json")) {
            return false;
        }
        return true;
    }

    /**
     * Рекурсивно удаляет директорию.
     *
     * @param string $dirPath  путь к папке для удаления
     * @throws Exception
     * @return bool <b>TRUE</b> если удаление успешно 
     */
    private function deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            // Попытка удалить директорию которой не существует
            throw new \InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath.'*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);

        return true;
    }

    /**
     * Извлекает JSON из файла и возвращает ввиде массива.
     *
     * @param string $dirPath  путь к папке для удаления
     * @throws Exception
     * @return array ассоциативный массив 
     */
    private function parseJSON($path)
    {
        $file = file_get_contents($path);
        // Исключительная ошибка чтения файла кэша
        if ($file === false) {
            throw new \Exception("Error reading cache file: {$this->dir}/{$key}.json");
        }
        $jsonArray = json_decode($file, true);
        // ошибки формата JSON
        if (json_last_error()) {
            throw new \Exception("Response JSON is invalid: {$this->dir}/{$key}.json");
        }

        return $jsonArray;
    }
}
