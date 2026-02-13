#!/usr/bin/env bash
#
# E2E Backup/Restore Test for All Database Types
#
# Tests the EXACT same dump/restore commands used by DatabaseBackupJob.
# For each DB type:
#   1. Seed known test data
#   2. Run backup command (same as production)
#   3. Verify backup file exists and is non-empty
#   4. Restore into the SAME container (after clearing data)
#   5. Verify data matches original
#
# Usage: ./tests/e2e-backup/run-e2e-backup-test.sh
#

set -uo pipefail
# Note: we do NOT use 'set -e' because test assertions use non-zero exit codes;
# critical commands (docker compose up, seeding) will fail visibly anyway.

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/backups"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
TOTAL=0

pass() {
    PASSED=$((PASSED + 1))
    TOTAL=$((TOTAL + 1))
    echo -e "  ${GREEN}PASS${NC} $1"
}

fail() {
    FAILED=$((FAILED + 1))
    TOTAL=$((TOTAL + 1))
    echo -e "  ${RED}FAIL${NC} $1"
    if [ -n "${2:-}" ]; then
        echo -e "       ${RED}$2${NC}"
    fi
}

section() {
    echo ""
    echo -e "${BLUE}━━━ $1 ━━━${NC}"
}

cleanup() {
    echo ""
    echo -e "${YELLOW}Cleaning up...${NC}"
    docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
    rm -rf "$BACKUP_DIR"
}

trap cleanup EXIT

# ─────────────────────────────────────────────
# Start containers
# ─────────────────────────────────────────────

echo -e "${BLUE}Starting test database containers...${NC}"
docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
docker compose -f "$COMPOSE_FILE" up -d

echo "Waiting for all containers to be healthy..."
TIMEOUT=90
ELAPSED=0
while true; do
    ALL_HEALTHY=true
    for SVC in test-postgres test-mysql test-mariadb test-mongodb test-redis test-clickhouse; do
        STATUS=$(docker compose -f "$COMPOSE_FILE" ps --format json "$SVC" 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('Health',''))" 2>/dev/null || echo "")
        if [ "$STATUS" != "healthy" ]; then
            ALL_HEALTHY=false
            break
        fi
    done
    if $ALL_HEALTHY; then
        echo -e "${GREEN}All containers healthy!${NC}"
        break
    fi
    if [ $ELAPSED -ge $TIMEOUT ]; then
        echo -e "${RED}Timeout waiting for containers${NC}"
        docker compose -f "$COMPOSE_FILE" ps
        exit 1
    fi
    sleep 2
    ELAPSED=$((ELAPSED + 2))
    echo -n "."
done
echo ""

mkdir -p "$BACKUP_DIR"

# ═══════════════════════════════════════════════
# POSTGRESQL
# ═══════════════════════════════════════════════

section "PostgreSQL (pg_dump --format=custom)"

# Seed data
docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb <<'SQL'
CREATE TABLE users (id SERIAL PRIMARY KEY, name TEXT NOT NULL, email TEXT UNIQUE, created_at TIMESTAMP DEFAULT NOW());
CREATE TABLE orders (id SERIAL PRIMARY KEY, user_id INT REFERENCES users(id), amount NUMERIC(10,2), status TEXT DEFAULT 'pending');
INSERT INTO users (name, email) VALUES ('Alice', 'alice@test.com'), ('Bob', 'bob@test.com'), ('Charlie', 'charlie@test.com');
INSERT INTO orders (user_id, amount, status) VALUES (1, 99.99, 'completed'), (1, 49.50, 'pending'), (2, 200.00, 'completed'), (3, 15.75, 'shipped');
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
-- Stored function
CREATE OR REPLACE FUNCTION get_user_total(uid INT) RETURNS NUMERIC AS $$
  SELECT COALESCE(SUM(amount), 0) FROM orders WHERE user_id = uid;
$$ LANGUAGE SQL;
SQL

PG_ROW_COUNT=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT COUNT(*) FROM users" | tr -d ' \n')
PG_ORDER_COUNT=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT COUNT(*) FROM orders" | tr -d ' \n')
PG_TOTAL=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT get_user_total(1)" | tr -d ' \n')

echo "  Seeded: users=$PG_ROW_COUNT, orders=$PG_ORDER_COUNT, user1_total=$PG_TOTAL"

# Backup (EXACT same command as DatabaseBackupJob)
docker compose -f "$COMPOSE_FILE" exec -T test-postgres pg_dump --format=custom --no-acl --no-owner --username testuser testdb > "$BACKUP_DIR/pg-dump.dmp"

PG_SIZE=$(stat -f%z "$BACKUP_DIR/pg-dump.dmp" 2>/dev/null || stat -c%s "$BACKUP_DIR/pg-dump.dmp")
if [ "$PG_SIZE" -gt 0 ]; then
    pass "Backup file created ($PG_SIZE bytes)"
else
    fail "Backup file is empty"
fi

# Verify format
PG_HEADER=$(xxd -l 5 "$BACKUP_DIR/pg-dump.dmp" | head -1)
if echo "$PG_HEADER" | grep -q "5047 444d 50"; then
    pass "File header is valid pg_dump custom format (PGDMP magic)"
else
    fail "File header doesn't match pg_dump custom format" "$PG_HEADER"
fi

# Copy dump into container for pg_restore (custom format requires seekable file, pipes don't work)
docker compose -f "$COMPOSE_FILE" cp "$BACKUP_DIR/pg-dump.dmp" test-postgres:/tmp/pg-dump.dmp

# Verify pg_restore can list contents
PG_RESTORE_LIST=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres pg_restore --list /tmp/pg-dump.dmp 2>&1 || true)
if echo "$PG_RESTORE_LIST" | grep -q "TABLE.*users"; then
    pass "pg_restore --list finds 'users' table"
else
    fail "pg_restore --list cannot find 'users' table"
fi
if echo "$PG_RESTORE_LIST" | grep -q "TABLE.*orders"; then
    pass "pg_restore --list finds 'orders' table"
else
    fail "pg_restore --list cannot find 'orders' table"
fi
if echo "$PG_RESTORE_LIST" | grep -q "FUNCTION.*get_user_total"; then
    pass "pg_restore --list finds stored function 'get_user_total'"
else
    fail "pg_restore --list cannot find stored function" "$PG_RESTORE_LIST"
fi
if echo "$PG_RESTORE_LIST" | grep -q "INDEX.*idx_orders"; then
    pass "pg_restore --list finds indexes"
else
    fail "pg_restore --list cannot find indexes"
fi

# Restore: drop and recreate
docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d postgres -c "DROP DATABASE testdb" 2>/dev/null
docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d postgres -c "CREATE DATABASE testdb"
docker compose -f "$COMPOSE_FILE" exec -T test-postgres pg_restore --username testuser --dbname testdb --no-owner --no-acl /tmp/pg-dump.dmp

PG_RESTORED_USERS=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT COUNT(*) FROM users" | tr -d ' \n')
PG_RESTORED_ORDERS=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT COUNT(*) FROM orders" | tr -d ' \n')
PG_RESTORED_TOTAL=$(docker compose -f "$COMPOSE_FILE" exec -T test-postgres psql -U testuser -d testdb -t -c "SELECT get_user_total(1)" | tr -d ' \n')

if [ "$PG_RESTORED_USERS" = "$PG_ROW_COUNT" ]; then
    pass "Users count matches after restore ($PG_RESTORED_USERS)"
else
    fail "Users count mismatch: expected $PG_ROW_COUNT, got $PG_RESTORED_USERS"
fi
if [ "$PG_RESTORED_ORDERS" = "$PG_ORDER_COUNT" ]; then
    pass "Orders count matches after restore ($PG_RESTORED_ORDERS)"
else
    fail "Orders count mismatch: expected $PG_ORDER_COUNT, got $PG_RESTORED_ORDERS"
fi
if [ "$PG_RESTORED_TOTAL" = "$PG_TOTAL" ]; then
    pass "Stored function works after restore (user1_total=$PG_RESTORED_TOTAL)"
else
    fail "Stored function result mismatch: expected $PG_TOTAL, got $PG_RESTORED_TOTAL"
fi

# ═══════════════════════════════════════════════
# MYSQL
# ═══════════════════════════════════════════════

section "MySQL (mysqldump --single-transaction --quick --routines --events)"

docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb <<'SQL'
CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10,2), category VARCHAR(100));
CREATE TABLE sales (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, quantity INT, sold_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (product_id) REFERENCES products(id));
INSERT INTO products (name, price, category) VALUES ('Widget', 19.99, 'tools'), ('Gadget', 49.99, 'electronics'), ('Doohickey', 9.99, 'tools');
INSERT INTO sales (product_id, quantity) VALUES (1, 5), (2, 3), (1, 2), (3, 10), (2, 1);
DELIMITER //
CREATE PROCEDURE get_sales_total(IN cat VARCHAR(100), OUT total DECIMAL(10,2))
BEGIN
  SELECT COALESCE(SUM(p.price * s.quantity), 0) INTO total FROM sales s JOIN products p ON p.id = s.product_id WHERE p.category = cat;
END //
DELIMITER ;
CREATE EVENT cleanup_old_sales ON SCHEDULE EVERY 1 DAY DO DELETE FROM sales WHERE sold_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
SQL

MY_PRODUCTS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM products" 2>/dev/null | tr -d ' \r\n')
MY_SALES=$(docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM sales" 2>/dev/null | tr -d ' \r\n')
echo "  Seeded: products=$MY_PRODUCTS, sales=$MY_SALES"

# Backup (EXACT same command as DatabaseBackupJob — now with --single-transaction --quick --routines --events)
docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysqldump -h 127.0.0.1 -u root -p"rootpass123" --single-transaction --quick --routines --events testdb > "$BACKUP_DIR/mysql-dump.dmp" 2>/dev/null

MY_SIZE=$(stat -f%z "$BACKUP_DIR/mysql-dump.dmp" 2>/dev/null || stat -c%s "$BACKUP_DIR/mysql-dump.dmp")
if [ "$MY_SIZE" -gt 0 ]; then
    pass "Backup file created ($MY_SIZE bytes)"
else
    fail "Backup file is empty"
fi

# Verify SQL content
if grep -q "CREATE TABLE.*products" "$BACKUP_DIR/mysql-dump.dmp"; then
    pass "Dump contains CREATE TABLE for products"
else
    fail "Dump missing CREATE TABLE for products"
fi
if grep -q "INSERT INTO.*products" "$BACKUP_DIR/mysql-dump.dmp"; then
    pass "Dump contains INSERT data for products"
else
    fail "Dump missing INSERT data"
fi
if grep -q "CREATE.*PROCEDURE.*get_sales_total" "$BACKUP_DIR/mysql-dump.dmp"; then
    pass "Dump contains stored procedure (--routines works)"
else
    fail "Dump MISSING stored procedure (--routines flag not working!)"
fi
if grep -q "CREATE.*EVENT.*cleanup_old_sales" "$BACKUP_DIR/mysql-dump.dmp" || grep -q "CREATE DEFINER.*EVENT" "$BACKUP_DIR/mysql-dump.dmp"; then
    pass "Dump contains event (--events works)"
else
    fail "Dump MISSING event (--events flag not working!)"
fi

# Restore
docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 -e "DROP DATABASE testdb; CREATE DATABASE testdb" 2>/dev/null
docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb < "$BACKUP_DIR/mysql-dump.dmp" 2>/dev/null

MY_R_PRODUCTS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM products" 2>/dev/null | tr -d ' \r\n')
MY_R_SALES=$(docker compose -f "$COMPOSE_FILE" exec -T test-mysql mysql -h 127.0.0.1 -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM sales" 2>/dev/null | tr -d ' \r\n')

if [ "$MY_R_PRODUCTS" = "$MY_PRODUCTS" ]; then
    pass "Products count matches after restore ($MY_R_PRODUCTS)"
else
    fail "Products count mismatch: expected $MY_PRODUCTS, got $MY_R_PRODUCTS"
fi
if [ "$MY_R_SALES" = "$MY_SALES" ]; then
    pass "Sales count matches after restore ($MY_R_SALES)"
else
    fail "Sales count mismatch: expected $MY_SALES, got $MY_R_SALES"
fi

# ═══════════════════════════════════════════════
# MARIADB
# ═══════════════════════════════════════════════

section "MariaDB (mariadb-dump --single-transaction --quick --routines --events)"

docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 testdb <<'SQL'
CREATE TABLE articles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(500) NOT NULL, body TEXT, published BOOLEAN DEFAULT FALSE);
INSERT INTO articles (title, body, published) VALUES ('First Post', 'Hello world with special chars: <>&"quotes"', TRUE), ('Draft', 'Not published yet', FALSE), ('Unicode Test', 'Привет мир! 日本語テスト 🎉', TRUE);
DELIMITER //
CREATE PROCEDURE count_published(OUT cnt INT)
BEGIN
  SELECT COUNT(*) INTO cnt FROM articles WHERE published = TRUE;
END //
DELIMITER ;
SQL

MA_COUNT=$(docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM articles" 2>/dev/null | tr -d ' \r\n')
echo "  Seeded: articles=$MA_COUNT (including unicode/special chars)"

# Backup
docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb-dump -u root -p"rootpass123" --single-transaction --quick --routines --events testdb > "$BACKUP_DIR/mariadb-dump.dmp" 2>/dev/null

MA_SIZE=$(stat -f%z "$BACKUP_DIR/mariadb-dump.dmp" 2>/dev/null || stat -c%s "$BACKUP_DIR/mariadb-dump.dmp")
if [ "$MA_SIZE" -gt 0 ]; then
    pass "Backup file created ($MA_SIZE bytes)"
else
    fail "Backup file is empty"
fi

if grep -q "CREATE TABLE.*articles" "$BACKUP_DIR/mariadb-dump.dmp"; then
    pass "Dump contains CREATE TABLE"
else
    fail "Dump missing CREATE TABLE"
fi
if grep -q "CREATE.*PROCEDURE.*count_published" "$BACKUP_DIR/mariadb-dump.dmp"; then
    pass "Dump contains stored procedure (--routines works)"
else
    fail "Dump MISSING stored procedure"
fi
# Check unicode preservation
if grep -q "Привет мир" "$BACKUP_DIR/mariadb-dump.dmp"; then
    pass "Unicode data preserved in dump (Cyrillic)"
else
    fail "Unicode data LOST in dump!"
fi

# Restore
docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 -e "DROP DATABASE testdb; CREATE DATABASE testdb" 2>/dev/null
docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 testdb < "$BACKUP_DIR/mariadb-dump.dmp" 2>/dev/null

MA_R_COUNT=$(docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 testdb -N -e "SELECT COUNT(*) FROM articles" 2>/dev/null | tr -d ' \r\n')
MA_R_UNICODE=$(docker compose -f "$COMPOSE_FILE" exec -T test-mariadb mariadb -u root -prootpass123 testdb -N -e "SELECT body FROM articles WHERE title='Unicode Test'" 2>/dev/null | tr -d '\r\n')

if [ "$MA_R_COUNT" = "$MA_COUNT" ]; then
    pass "Article count matches after restore ($MA_R_COUNT)"
else
    fail "Article count mismatch: expected $MA_COUNT, got $MA_R_COUNT"
fi
if echo "$MA_R_UNICODE" | grep -q "Привет мир"; then
    pass "Unicode data intact after restore"
else
    fail "Unicode data CORRUPTED after restore: $MA_R_UNICODE"
fi

# ═══════════════════════════════════════════════
# MONGODB
# ═══════════════════════════════════════════════

section "MongoDB (mongodump --gzip --archive)"

docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --eval '
db.customers.insertMany([
  { name: "Alice", email: "alice@test.com", tags: ["vip", "active"], nested: { address: { city: "NYC" } } },
  { name: "Bob", email: "bob@test.com", tags: ["active"], nested: { address: { city: "LA" } } },
  { name: "Charlie", email: "charlie@test.com", tags: [], nested: { address: { city: "Москва" } } }
]);
db.logs.insertMany([
  { level: "info", message: "System started", ts: new Date() },
  { level: "error", message: "Connection failed", ts: new Date() },
  { level: "warn", message: "Disk 90% full", ts: new Date() },
  { level: "info", message: "Backup completed", ts: new Date() }
]);
'

MO_CUSTOMERS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --quiet --eval 'db.customers.countDocuments()' | tr -d ' \r\n')
MO_LOGS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --quiet --eval 'db.logs.countDocuments()' | tr -d ' \r\n')
echo "  Seeded: customers=$MO_CUSTOMERS, logs=$MO_LOGS"

# Backup (EXACT same as DatabaseBackupJob)
docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongodump --authenticationDatabase=admin --uri="mongodb://root:rootpass123@localhost:27017" --db testdb --gzip --archive > "$BACKUP_DIR/mongo-dump.tar.gz"

MO_SIZE=$(stat -f%z "$BACKUP_DIR/mongo-dump.tar.gz" 2>/dev/null || stat -c%s "$BACKUP_DIR/mongo-dump.tar.gz")
if [ "$MO_SIZE" -gt 0 ]; then
    pass "Backup file created ($MO_SIZE bytes)"
else
    fail "Backup file is empty"
fi

# Copy archive into container for verification and restore (binary data through stdin can be unreliable)
docker compose -f "$COMPOSE_FILE" cp "$BACKUP_DIR/mongo-dump.tar.gz" test-mongodb:/tmp/mongo-dump.tar.gz

# Verify it's a valid archive
MO_LIST=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongorestore --gzip --archive=/tmp/mongo-dump.tar.gz --dryRun --nsInclude='testdb.*' 2>&1 || true)
if echo "$MO_LIST" | grep -qi "customers"; then
    pass "Archive contains 'customers' collection"
else
    # mongorestore --dryRun may not list collections, just check it doesn't error
    if echo "$MO_LIST" | grep -qi "error"; then
        fail "Archive appears invalid" "$MO_LIST"
    else
        pass "Archive accepted by mongorestore (no errors)"
    fi
fi

# Restore
docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin --eval 'db.getSiblingDB("testdb").dropDatabase()'
docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongorestore --authenticationDatabase=admin --uri="mongodb://root:rootpass123@localhost:27017" --gzip --archive=/tmp/mongo-dump.tar.gz --drop 2>/dev/null

MO_R_CUSTOMERS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --quiet --eval 'db.customers.countDocuments()' | tr -d ' \r\n')
MO_R_LOGS=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --quiet --eval 'db.logs.countDocuments()' | tr -d ' \r\n')

if [ "$MO_R_CUSTOMERS" = "$MO_CUSTOMERS" ]; then
    pass "Customers count matches after restore ($MO_R_CUSTOMERS)"
else
    fail "Customers count mismatch: expected $MO_CUSTOMERS, got $MO_R_CUSTOMERS"
fi
if [ "$MO_R_LOGS" = "$MO_LOGS" ]; then
    pass "Logs count matches after restore ($MO_R_LOGS)"
else
    fail "Logs count mismatch: expected $MO_LOGS, got $MO_R_LOGS"
fi

# Verify nested data integrity
MO_R_CITY=$(docker compose -f "$COMPOSE_FILE" exec -T test-mongodb mongosh -u root -p rootpass123 --authenticationDatabase admin testdb --quiet --eval 'db.customers.findOne({name:"Charlie"}).nested.address.city' | tr -d ' \r\n"')
if [ "$MO_R_CITY" = "Москва" ]; then
    pass "Nested unicode data intact after restore (city=Москва)"
else
    fail "Nested data corrupted: expected 'Москва', got '$MO_R_CITY'"
fi

# ═══════════════════════════════════════════════
# REDIS
# ═══════════════════════════════════════════════

section "Redis (SAVE + RDB dump)"

docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning <<'REDIS'
SET user:1 '{"name":"Alice","email":"alice@test.com"}'
SET user:2 '{"name":"Bob","email":"bob@test.com"}'
SET counter:visits 42
HSET config:app theme dark language en timeout 3600
LPUSH queue:tasks "task1" "task2" "task3"
SADD tags:active "user:1" "user:2"
ZADD leaderboard 100 "Alice" 85 "Bob" 92 "Charlie"
REDIS

RE_KEYS=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning DBSIZE | sed 's/[^0-9]//g')
RE_COUNTER=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning GET counter:visits | tr -d '\r\n')
echo "  Seeded: keys=$RE_KEYS, counter=$RE_COUNTER"

# Backup (EXACT same as DatabaseBackupJob — SAVE + cat /data/dump.rdb)
docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning SAVE
docker compose -f "$COMPOSE_FILE" exec -T test-redis cat /data/dump.rdb > "$BACKUP_DIR/redis-dump.rdb"

RE_SIZE=$(stat -f%z "$BACKUP_DIR/redis-dump.rdb" 2>/dev/null || stat -c%s "$BACKUP_DIR/redis-dump.rdb")
if [ "$RE_SIZE" -gt 0 ]; then
    pass "Backup file created ($RE_SIZE bytes)"
else
    fail "Backup file is empty"
fi

# Verify RDB magic header
RE_HEADER=$(xxd -l 5 "$BACKUP_DIR/redis-dump.rdb" | head -1)
if echo "$RE_HEADER" | grep -q "5245 4449 53"; then
    pass "File header is valid RDB format (REDIS magic bytes)"
else
    fail "File header doesn't match RDB format" "$RE_HEADER"
fi

# Restore: stop container, replace RDB, start container
# (must stop first so Redis doesn't overwrite our RDB on shutdown)
docker compose -f "$COMPOSE_FILE" stop test-redis
docker compose -f "$COMPOSE_FILE" cp "$BACKUP_DIR/redis-dump.rdb" test-redis:/data/dump.rdb
docker compose -f "$COMPOSE_FILE" start test-redis

# Wait for redis to be healthy again
sleep 3
for i in $(seq 1 15); do
    if docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning PING 2>/dev/null | grep -q PONG; then
        break
    fi
    sleep 1
done

RE_R_KEYS=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning DBSIZE | sed 's/[^0-9]//g')
RE_R_COUNTER=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning GET counter:visits | tr -d '\r\n')
RE_R_HASH=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning HGET config:app theme | tr -d '\r\n')
RE_R_SCORE=$(docker compose -f "$COMPOSE_FILE" exec -T test-redis redis-cli -a testpass123 --no-auth-warning ZSCORE leaderboard Alice | tr -d '\r\n')

if [ "$RE_R_KEYS" = "$RE_KEYS" ]; then
    pass "Key count matches after restore ($RE_R_KEYS)"
else
    fail "Key count mismatch: expected $RE_KEYS, got $RE_R_KEYS"
fi
if [ "$RE_R_COUNTER" = "$RE_COUNTER" ]; then
    pass "Counter value matches after restore ($RE_R_COUNTER)"
else
    fail "Counter mismatch: expected $RE_COUNTER, got $RE_R_COUNTER"
fi
if [ "$RE_R_HASH" = "dark" ]; then
    pass "Hash data intact after restore (theme=dark)"
else
    fail "Hash data corrupted: expected 'dark', got '$RE_R_HASH'"
fi
if [ "$RE_R_SCORE" = "100" ]; then
    pass "Sorted set data intact after restore (Alice score=100)"
else
    fail "Sorted set data corrupted: expected '100', got '$RE_R_SCORE'"
fi

# ═══════════════════════════════════════════════
# CLICKHOUSE
# ═══════════════════════════════════════════════

section "ClickHouse (DDL + Native format data → tar.gz)"

docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --multiquery <<'SQL'
CREATE TABLE IF NOT EXISTS events (
    id UInt64,
    event_type String,
    payload String,
    created_at DateTime DEFAULT now()
) ENGINE = MergeTree() ORDER BY (id, created_at);

CREATE TABLE IF NOT EXISTS metrics (
    ts DateTime,
    name String,
    value Float64
) ENGINE = MergeTree() ORDER BY (ts, name);

INSERT INTO events (id, event_type, payload) VALUES (1, 'login', '{"user":"alice"}'), (2, 'purchase', '{"item":"widget","price":19.99}'), (3, 'login', '{"user":"bob"}'), (4, 'logout', '{"user":"alice"}'), (5, 'error', '{"msg":"Ошибка подключения"}');

INSERT INTO metrics (ts, name, value) VALUES ('2024-01-01 00:00:00', 'cpu', 45.5), ('2024-01-01 00:01:00', 'cpu', 52.3), ('2024-01-01 00:02:00', 'memory', 78.1);
SQL

CH_EVENTS=$(docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "SELECT count() FROM events" | tr -d ' \r\n')
CH_METRICS=$(docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "SELECT count() FROM metrics" | tr -d ' \r\n')
echo "  Seeded: events=$CH_EVENTS, metrics=$CH_METRICS"

# Backup (same as DatabaseBackupJob — DDL + Native data → tar.gz)
# NOTE: --format TSVRaw is critical for DDL — without it SHOW CREATE TABLE escapes backslashes
CH_TMP="/tmp/ch_backup_test"
docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse bash -c "
    mkdir -p $CH_TMP && \
    clickhouse-client --user 'testuser' --password 'testpass123' -d 'testdb' --query 'SHOW TABLES' | while read -r tbl; do \
        clickhouse-client --user 'testuser' --password 'testpass123' -d 'testdb' --query \"SHOW CREATE TABLE \\\"\${tbl}\\\"\" --format TSVRaw > ${CH_TMP}/\${tbl}.sql; \
        clickhouse-client --user 'testuser' --password 'testpass123' -d 'testdb' --query \"SELECT * FROM \\\"\${tbl}\\\" FORMAT Native\" > ${CH_TMP}/\${tbl}.native; \
    done && \
    cd /tmp && tar czf ch_backup.tar.gz -C ${CH_TMP} . && \
    rm -rf ${CH_TMP}
"
docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse cat /tmp/ch_backup.tar.gz > "$BACKUP_DIR/clickhouse-dump.tar.gz"
docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse rm -f /tmp/ch_backup.tar.gz

CH_SIZE=$(stat -f%z "$BACKUP_DIR/clickhouse-dump.tar.gz" 2>/dev/null || stat -c%s "$BACKUP_DIR/clickhouse-dump.tar.gz")
if [ "$CH_SIZE" -gt 0 ]; then
    pass "Backup file created ($CH_SIZE bytes)"
else
    fail "Backup file is empty"
fi

# Verify tar.gz contents
CH_FILES=$(tar tzf "$BACKUP_DIR/clickhouse-dump.tar.gz" | sort)
if echo "$CH_FILES" | grep -q "events.sql"; then
    pass "Archive contains events.sql (DDL)"
else
    fail "Archive missing events.sql"
fi
if echo "$CH_FILES" | grep -q "events.native"; then
    pass "Archive contains events.native (data)"
else
    fail "Archive missing events.native"
fi
if echo "$CH_FILES" | grep -q "metrics.sql"; then
    pass "Archive contains metrics.sql (DDL)"
else
    fail "Archive missing metrics.sql"
fi
if echo "$CH_FILES" | grep -q "metrics.native"; then
    pass "Archive contains metrics.native (data)"
else
    fail "Archive missing metrics.native"
fi

# Verify DDL content
EVENTS_DDL=$(tar xzf "$BACKUP_DIR/clickhouse-dump.tar.gz" -O ./events.sql 2>/dev/null || tar xzf "$BACKUP_DIR/clickhouse-dump.tar.gz" -O events.sql)
if echo "$EVENTS_DDL" | grep -q "MergeTree"; then
    pass "DDL contains correct engine (MergeTree)"
else
    fail "DDL missing engine definition" "$EVENTS_DDL"
fi

# Restore: drop tables, recreate from DDL, load native data
docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --multiquery --query "DROP TABLE IF EXISTS events; DROP TABLE IF EXISTS metrics;"

# Extract backup files locally
mkdir -p "$BACKUP_DIR/ch_extracted"
tar xzf "$BACKUP_DIR/clickhouse-dump.tar.gz" -C "$BACKUP_DIR/ch_extracted"

# Restore DDL (pipe SQL via stdin to avoid shell quoting issues)
for sql_file in "$BACKUP_DIR"/ch_extracted/*.sql; do
    docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb < "$sql_file"
done

# Restore data
for native_file in "$BACKUP_DIR"/ch_extracted/*.native; do
    TABLE_NAME=$(basename "$native_file" .native)
    docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "INSERT INTO \"${TABLE_NAME}\" FORMAT Native" < "$native_file"
done

CH_R_EVENTS=$(docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "SELECT count() FROM events" | tr -d ' \r\n')
CH_R_METRICS=$(docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "SELECT count() FROM metrics" | tr -d ' \r\n')

if [ "$CH_R_EVENTS" = "$CH_EVENTS" ]; then
    pass "Events count matches after restore ($CH_R_EVENTS)"
else
    fail "Events count mismatch: expected $CH_EVENTS, got $CH_R_EVENTS"
fi
if [ "$CH_R_METRICS" = "$CH_METRICS" ]; then
    pass "Metrics count matches after restore ($CH_R_METRICS)"
else
    fail "Metrics count mismatch: expected $CH_METRICS, got $CH_R_METRICS"
fi

# Verify unicode data
CH_R_UNICODE=$(docker compose -f "$COMPOSE_FILE" exec -T test-clickhouse clickhouse-client --user testuser --password testpass123 -d testdb --query "SELECT payload FROM events WHERE id=5" | tr -d '\r\n')
if echo "$CH_R_UNICODE" | grep -q "Ошибка"; then
    pass "Unicode data intact after restore (Cyrillic in ClickHouse)"
else
    fail "Unicode data corrupted: $CH_R_UNICODE"
fi

# ═══════════════════════════════════════════════
# SUMMARY
# ═══════════════════════════════════════════════

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════${NC}"
echo -e "${BLUE}  E2E BACKUP TEST RESULTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════${NC}"
echo ""
echo -e "  Total:  $TOTAL"
echo -e "  ${GREEN}Passed: $PASSED${NC}"
if [ $FAILED -gt 0 ]; then
    echo -e "  ${RED}Failed: $FAILED${NC}"
    echo ""
    exit 1
else
    echo -e "  Failed: 0"
    echo ""
    echo -e "  ${GREEN}ALL TESTS PASSED${NC}"
    echo ""
fi
