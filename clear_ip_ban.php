<?php
/**
 * 一键清理自动封禁IP记录
 * 上传到网站根目录（和 app/、frame/ 同级）后，浏览器访问一次即可。
 * 使用完毕请立即删除此文件！
 */

$host    = '127.0.0.1';
$port    = '3306';
$dbname  = 'lgh';
$user    = 'lgh';
$pass    = '33TaWK2bYbFBjPGy';
$charset = 'utf8';
$prefix  = 'dd_';
$table   = $prefix . 'ip_ban';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 检查表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() === 0) {
        die('ip_ban 表不存在，无需清理。请删除此文件。');
    }

    // 查一下有哪些自动封禁记录
    $stmt = $pdo->prepare("SELECT id, ip, reason FROM `{$table}` WHERE ban_type = 'auto' AND status = 1");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        die('没有自动封禁记录，无需清理。请删除此文件。');
    }

    // 解除封禁
    $stmt = $pdo->prepare("UPDATE `{$table}` SET status = 0 WHERE ban_type = 'auto' AND status = 1");
    $stmt->execute();
    $count = $stmt->rowCount();

    echo "<h3>已解除 {$count} 条自动封禁记录：</h3><ul>";
    foreach ($rows as $row) {
        echo "<li>IP: {$row['ip']} &mdash; {$row['reason']}</li>";
    }
    echo "</ul><p style='color:red;font-weight:bold;'>操作完成，请立即删除 clear_ip_ban.php 文件！</p>";

} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}
