<?php
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

function respond(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function body(): array {
    $data = json_decode(file_get_contents('php://input'));
    if (!$data instanceof stdClass) {
        respond(400, ['error' => 'Send a JSON object']);
    }
    return (array) $data;
}

function name_field(array $data): string {
    $name = $data['name'] ?? null;
    if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 100) {
        respond(400, ['error' => 'name must contain 1 to 100 bytes']);
    }
    return trim($name);
}

function number_field(array $data, string $field, float $min, float $max): float {
    $value = $data[$field] ?? null;
    if ((!is_int($value) && !is_float($value)) || $value < $min || $value > $max) {
        respond(400, ['error' => "$field must be a number from $min to $max"]);
    }
    return $value;
}

function query(PDO $db, string $sql, array $values = []): PDOStatement {
    $statement = $db->prepare($sql);
    $statement->execute($values);
    return $statement;
}

function tank_exists(PDO $db, int $id): void {
    if (!query($db, 'SELECT id FROM tanks WHERE id = ?', [$id])->fetch()) {
        respond(404, ['error' => 'Tank not found']);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$routes = [
    'tanks' => ['GET', 'POST'],
    'tank' => ['GET', 'PUT', 'DELETE'],
    'tank_readings' => ['GET'],
    'tank_plants' => ['GET'],
    'readings' => ['POST'],
    'reading' => ['DELETE'],
    'plants' => ['POST']
];
$route = null;
$id = null;
if (in_array($path, ['tanks', 'readings', 'plants'], true)) {
    $route = $path;
} elseif (preg_match('~^(tanks|readings)/([^/]+)(?:/(readings|plants))?$~', $path, $parts)) {
    if ($parts[1] === 'readings' && isset($parts[3])) {
        respond(404, ['error' => 'Route not found']);
    }
    if (!ctype_digit($parts[2]) || strlen($parts[2]) > 10 || (int)$parts[2] < 1 || (int)$parts[2] > 2147483647) {
        respond(400, ['error' => 'ID must be a positive integer']);
    }
    $id = (int)$parts[2];
    $route = $parts[1] === 'readings' ? 'reading' : (isset($parts[3]) ? 'tank_' . $parts[3] : 'tank');
}
if (!$route) respond(404, ['error' => 'Route not found']);
if (!in_array($method, $routes[$route], true)) {
    header('Allow: ' . implode(', ', $routes[$route]));
    respond(405, ['error' => 'Method not allowed']);
}
if (($route === 'tanks' && $method === 'POST')
    || ($route === 'tank' && $method === 'DELETE')
    || $route === 'readings') {
    require_token();
}

try {
    $db = db();
    if ($route === 'tanks' && $method === 'GET') {
        respond(200, query($db, 'SELECT * FROM tanks ORDER BY id')->fetchAll());
    }
    if (in_array($route, ['tank', 'tank_readings', 'tank_plants'], true)) {
        tank_exists($db, $id);
    }
    if ($route === 'tank' && $method === 'GET') {
        respond(200, query($db, 'SELECT * FROM tanks WHERE id = ?', [$id])->fetch());
    }
    if ($route === 'tanks' || ($route === 'tank' && $method === 'PUT')) {
        $data = body();
        $name = name_field($data);
        $gallons = number_field($data, 'gallons', 0.01, 999999.99);
        if ($method === 'POST') {
            query($db, 'INSERT INTO tanks (name, gallons) VALUES (?, ?)', [$name, $gallons]);
            respond(201, ['id' => (int)$db->lastInsertId(), 'message' => 'Tank created']);
        }
        query($db, 'UPDATE tanks SET name = ?, gallons = ? WHERE id = ?', [$name, $gallons, $id]);
        respond(200, ['message' => 'Tank updated']);
    }
    if ($route === 'tank' && $method === 'DELETE') {
        query($db, 'DELETE FROM tanks WHERE id = ?', [$id]);
        respond(200, ['message' => 'Tank and its readings and plants deleted']);
    }
    if ($route === 'tank_readings') {
        respond(200, query($db, 'SELECT * FROM readings WHERE tank_id = ? ORDER BY id', [$id])->fetchAll());
    }
    if ($route === 'tank_plants') {
        respond(200, query($db, 'SELECT * FROM plants WHERE tank_id = ? ORDER BY id', [$id])->fetchAll());
    }
    if ($route === 'reading') {
        $statement = query($db, 'DELETE FROM readings WHERE id = ?', [$id]);
        if (!$statement->rowCount()) respond(404, ['error' => 'Reading not found']);
        respond(200, ['message' => 'Reading deleted']);
    }
    $data = body();
    $tankId = $data['tank_id'] ?? null;
    if (!is_int($tankId) || $tankId < 1 || $tankId > 2147483647) {
        respond(400, ['error' => 'tank_id must be a positive integer']);
    }
    tank_exists($db, $tankId);
    if ($route === 'readings') {
        $ph = number_field($data, 'ph', 0, 14);
        $temperature = number_field($data, 'temperature_c', 0, 50);
        query($db, 'INSERT INTO readings (tank_id, ph, temperature_c) VALUES (?, ?, ?)', [$tankId, $ph, $temperature]);
    } else {
        $name = name_field($data);
        query($db, 'INSERT INTO plants (tank_id, name) VALUES (?, ?)', [$tankId, $name]);
    }
    respond(201, ['id' => (int)$db->lastInsertId(), 'message' => 'Record created']);
} catch (PDOException $error) {
    error_log($error->getMessage());
    respond(500, ['error' => 'Database operation failed']);
}
