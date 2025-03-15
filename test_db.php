<?php
try {
    $db = new PDO("pgsql:host=" . parse_url(getenv("DATABASE_URL"))["host"] . ";port=" . parse_url(getenv("DATABASE_URL"))["port"] . ";dbname=" . ltrim(parse_url(getenv("DATABASE_URL"))["path"], "/") . ";user=" . parse_url(getenv("DATABASE_URL"))["user"] . ";password=" . parse_url(getenv("DATABASE_URL"))["pass"]);
    echo "اتصال موفق!";
} catch (PDOException $e) {
    echo "خطا: " . $e->getMessage();
}
?>