<?php

/**
 * @param $host
 * @param $dbName
 * @param null $user
 * @param null $password
 * @return PDO
 */
function getConnection($host, $dbName, $user = null, $password = null): PDO
{
    return new PDO("mysql:host=$host;dbname=$dbName", $user, $password);
}

/**
 * @param PDO $connection
 * @param string $ipAddress
 * @param string $userAgent
 * @param string $pageUrl
 * @return mixed
 * @throws Exception
 */
function get(PDO $connection, string $ipAddress, string $userAgent, string $pageUrl)
{
    $sql = "SELECT * FROM view_log 
    WHERE 
        ip_address = :ip_address
    AND
        user_agent = :user_agent
    AND
        page_url = :page_url";

    $statement = $connection->prepare($sql);
    $statement->bindValue(':ip_address', $ipAddress);
    $statement->bindValue(':user_agent', $userAgent);
    $statement->bindValue(':page_url', $pageUrl);
    $statement->execute();

    return $statement->fetch(PDO::FETCH_ASSOC);
}

/**
 * @param PDO $connection
 * @param array $data
 * @return bool
 * @throws Exception
 */
function insert(PDO $connection, array $data): bool
{
    $sql = "INSERT INTO view_log 
            SET
                ip_address = :ip_address,
                user_agent = :user_agent,
                view_date = :view_date,
                page_url = :page_url,
                views_count = 1";

    $statement = $connection->prepare($sql);
    $statement->bindValue(':ip_address', $data['ip_address']);
    $statement->bindValue(':user_agent', $data['user_agent']);
    $statement->bindValue(':view_date', $data['view_date']);
    $statement->bindValue(':page_url', $data['page_url']);

    if (!$statement->execute()) {
        $error = $statement->errorInfo();
        throw new Exception($error[2]);
    }

    return true;
}

/**
 * @param PDO $connection
 * @param string $ipAddress
 * @param string $userAgent
 * @param string $pageUrl
 * @return bool
 * @throws Exception
 */
function update(PDO $connection, string $ipAddress, string $userAgent, string $pageUrl)
{
    $sql = "UPDATE view_log 
            SET
                views_count = (views_count + 1)
            WHERE
                ip_address = :ip_address
            AND
                user_agent = :user_agent
            AND
                page_url = :page_url";

    $statement = $connection->prepare($sql);
    $statement->bindValue(':ip_address', $ipAddress);
    $statement->bindValue(':user_agent', $userAgent);
    $statement->bindValue(':page_url', $pageUrl);

    if (!$statement->execute()) {
        $error = $statement->errorInfo();
        throw new Exception($error[2]);
    }

    return true;
}

/**
 * @param string $source
 * @throws Exception
 */
function viewImage(string $source)
{
    if(!file_exists($source)) {
        throw new Exception('Cannot read file: ' . $source);
    }

    if (!$info = getimagesize($source)) {
        throw new Exception('Unsupported format: ' . $info['mime']);
    }

    $info = getimagesize($source);

    header('contentType: ' . $info['mime']);

    readfile($source);
}

/**
 * @return array
 */
function getRequestData(): array
{
    return [
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'view_date' => (new DateTime())->format('Y-m-d h:i:s'),
        'page_url' => $_SERVER['REQUEST_URI'],
    ];
}

/**
 * @throws Exception
 */
function writeLog()
{
    $connection = getConnection('localhost', 'test', 'root', 'root');

    $requestData = getRequestData();

    $record = get($connection, $requestData['ip_address'], $requestData['user_agent'], $requestData['page_url']);

    if ($record) {
        update($connection, $requestData['ip_address'], $requestData['user_agent'], $requestData['page_url']);
    } else {
        insert($connection, $requestData);
    }
}

try {
    viewImage('banner.jpg');
    writeLog();
} catch (Exception $exception) {
    echo $exception->getMessage();
};
