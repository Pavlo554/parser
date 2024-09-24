<?php
// Функція для очищення тексту від зайвих пробілів
function cleanText($text) {
    return trim(preg_replace('/\s+/', ' ', $text));
}

// Функція для отримання вмісту сторінки
function getPageContent($url) {
    $options = array(
        'http' => array(
            'method' => "GET",
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3\r\n"
        )
    );
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// Функція для парсингу сторінки
function parsePage($url) {
    $html = getPageContent($url);
    $data = [];

    // Парсимо назву продукту
    preg_match('/<h1[^>]*>(.*?)<span/s', $html, $matches);
    $data['product_name'] = isset($matches[1]) ? cleanText(strip_tags($matches[1])) : 'Невідомий продукт';
    
    // Парсимо ціну
    preg_match('/<div class="text-red-600.*?>(\d+)\s*<span.*?>₴<\/span>/s', $html, $matches);
    $data['product_price'] = isset($matches[1]) ? (float)$matches[1] : 0.0;

    // Опис продукту
    preg_match('/<div class="content px-2[^>]*>(.*?)<ul/s', $html, $matches);
    $data['product_description'] = isset($matches[1]) ? cleanText(strip_tags($matches[1])) : 'Невідомо';

    // Парсимо бренд
    preg_match('/Бренд:\s*<a.*?>(.*?)<\/a>/s', $html, $matches);
    $data['brand'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомо';

    // Парсимо серію
    preg_match('/Серия:\s*<a.*?>(.*?)<\/a>/s', $html, $matches);
    $data['series'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомо';

    // Парсимо тип
    preg_match('/Тип:\s*<a.*?>(.*?)<\/a>/s', $html, $matches);
    $data['type'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомо';

    // Парсимо код товару
    preg_match('/Код товара:\s*([\w\s+]+)/i', $html, $matches);
    $data['product_code'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомий код';

    // Парсимо конструкцію
    preg_match('/Конструкция:\s*(.*?)<br\s*\/>/s', $html, $matches);
    $data['construction'] = isset($matches[1]) ? cleanText(strip_tags($matches[1])) : 'Невідомо';
    // Парсимо особливість
    preg_match('/Особенность:\s*(.*?)<\/li>/s', $html, $matches);
    $data['feature'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомо';

    // Парсимо розмір двірників
    preg_match('/Размер:\s*(\d+)\s*мм.*?(\d+)\s*мм/s', $html, $matches);
    $data['driver_wiper_size'] = isset($matches[1]) ? $matches[1] . ' мм' : 'Невідомо';
    $data['passenger_wiper_size'] = isset($matches[2]) ? $matches[2] . ' мм' : 'Невідомо';

    // Парсимо інформацію про авто
    preg_match('/Подходит на:\s*<a.*?>(.*?)<\/a>/s', $html, $matches);
    if (isset($matches[1])) {
        $carInfo = cleanText($matches[1]);
        preg_match('/(.+)\s+(\d{4})-(\d{4})/', $carInfo, $carMatches);
        if (count($carMatches) == 4) {
            $data['make'] = explode(' ', $carMatches[1])[0];
            $data['model'] = implode(' ', array_slice(explode(' ', $carMatches[1]), 1));
            $data['year'] = $carMatches[2] . '-' . $carMatches[3];
        }
    } else {
        $data['make'] = $data['model'] = 'Невідомо';
        $data['year'] = 'Невідомо';
    }

    // Парсимо тип кріплення
    preg_match('/Крепление:\s*(.*?)<\/li>/s', $html, $matches);
    $data['mounting_type'] = isset($matches[1]) ? cleanText($matches[1]) : 'Невідомо';

    // Визначаємо категорію (передні чи задні)
    $data['category'] = strpos(strtolower($data['product_name']), 'задн') !== false ? 'Задні' : 'Передні';

    return $data;
}

// Перевіряємо, чи був надісланий URL через форму
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_url'])) {
    $url = $_POST['product_url'];
    
    // Підключення до бази даних
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "ggggg";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Отримуємо дані
    $productData = parsePage($url);

    // Підготовка SQL запиту
    $sql = "INSERT INTO products (category, product_name, product_price, make, model, product_description, year, mounting_type, 
            driver_wiper_size, passenger_wiper_size, type, product_code, series, brand, construction, feature) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Перевіряємо, чи кількість параметрів відповідає кількості полів
    $stmt = $conn->prepare($sql);

    // Визначаємо типи даних для bind_param
    $stmt->bind_param("ssdsssssssssssss", 
        $productData['category'],            // s
        $productData['product_name'],        // s
        $productData['product_price'],       // d
        $productData['make'],                // s
        $productData['model'],               // s
        $productData['product_description'], // s
        $productData['year'],                // s
        $productData['mounting_type'],       // s
        $productData['driver_wiper_size'],   // s
        $productData['passenger_wiper_size'],// s
        $productData['type'],                // s
        $productData['product_code'],        // s
        $productData['series'],              // s
        $productData['brand'],               // s
        $productData['construction'],        // s
        $productData['feature']              // s
    );

    // Виконання запиту
    if ($stmt->execute()) {
        echo "Новий запис успішно додано<br>";
        echo "<a href='index.html'>Повернутися на головну</a>";
    } else {
        echo "Помилка: " . $stmt->error . "<br>";
        echo "<a href='index.html'>Повернутися на головну</a>";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Будь ласка, використовуйте форму для надсилання URL.<br>";
    echo "<a href='index.html'>Повернутися на головну</a>";
}
?>
