<?php

declare(strict_types=1);

namespace Xkhm\Repository;

use PDO;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * The catalogue, against the real WHMCS 5.3.9 schema.
 *
 * The prices live in `tblpricing`, **one row per product and currency**,
 * with a column per billing cycle. A cycle that the product does not
 * have is `-1.00` — the WHMCS's own way of saying "not available".
 */
class CatalogueRepositoryPDO implements CatalogueRepositoryInterface
{
    /** The billing cycles, and the column of `tblpricing` that holds them. */
    public const CYCLES = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semiannually' => 'Semi Annually',
        'annually' => 'Annually',
        'biennially' => 'Biennially',
        'triennially' => 'Triennially',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $config = new Config();
        $this->pdo = $pdo ?? Connection::get($config->getDbConfig());
    }

    // ─── The products ───────────────────────────────────────────────────────

    // ─── The products ───────────────────────────────────────────────────────

    public function products(?int $groupId = null): array
    {
        $sql = 'SELECT p.*, g.name AS groupname FROM tblproducts p '
            . 'LEFT JOIN tblproductgroups g ON g.id = p.gid';

        $parameters = [];

        if ($groupId !== null) {
            $sql .= ' WHERE p.gid = ?';
            $parameters[] = $groupId;
        }

        $sql .= ' ORDER BY p.id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $index => $product) {
            $products[$index]['prices'] = $this->productPrices((int)$product['id']);
        }

        return $products;
    }

    public function product(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, g.name AS groupname FROM tblproducts p '
            . 'LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE p.id = ?'
        );
        $stmt->execute([$id]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product === false) {
            return null;
        }

        $product['prices'] = $this->productPrices($id);

        return $product;
    }

    public function productPrices(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT monthly, quarterly, semiannually, annually, biennially, triennially, '
            . 'msetupfee, ssetupfee FROM tblpricing '
            . 'WHERE type = ? AND relid = ? AND currency = ? LIMIT 1'
        );
        $stmt->execute(['product', $productId, 1]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return [];
        }

        $prices = [];

        foreach (array_keys(self::CYCLES) as $column) {
            $prices[$column] = (float)($row[$column] ?? -1);
        }

        $prices['setup'] = (float)($row['ssetupfee'] ?? 0);

        return $prices;
    }

    public function saveProduct(array $data, int $id = 0): int
    {
        $columns = [
            'name' => (string)($data['name'] ?? ''),
            'type' => (string)($data['type'] ?? 'other'),
            'gid' => (int)($data['gid'] ?? 0),
            'servergroup' => (int)($data['servergroup'] ?? 0),
            'hidden' => (string)($data['hidden'] ?? ''),
            'paytype' => (string)($data['paytype'] ?? 'free'),
            'proratabilling' => (string)($data['proratabilling'] ?? 'no'),
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($columns)));
            $columns['id'] = $id;

            $stmt = $this->pdo->prepare("UPDATE tblproducts SET $set WHERE id = :id");
            $stmt->execute($columns);

            return $id;
        }

        // As colunas que a tabela exige e que o painel não expõe.
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, 'tblproducts', array_keys($columns)),
            $columns
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblproducts ($names) VALUES ($placeholders)");
        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }
    public function saveProductPrices(int $productId, array $prices): bool
    {
        // A linha da tabela de preços: uma por produto e moeda.
        $existing = $this->pdo->prepare(
            'SELECT id FROM tblpricing WHERE type = ? AND relid = ? AND currency = ? LIMIT 1'
        );
        $existing->execute(['product', $productId, 1]);

        $rowId = $existing->fetchColumn();

        $values = [
            'msetupfee' => (float)($prices['setup'] ?? 0),
            'qsetupfee' => 0,
            'ssetupfee' => (float)($prices['setup'] ?? 0),
            'asetupfee' => 0,
            'bsetupfee' => 0,
            'tsetupfee' => 0,
        ];

        foreach (array_keys(self::CYCLES) as $column) {
            $values[$column] = (float)($prices[$column] ?? -1);
        }

        if ($rowId !== false) {
            $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($values)));
            $values['id'] = (int)$rowId;

            $stmt = $this->pdo->prepare("UPDATE tblpricing SET $set WHERE id = :id");

            return $stmt->execute($values);
        }

        $values['type'] = 'product';
        $values['relid'] = $productId;
        $values['currency'] = 1;

        // As colunas que a tabela exige e que o painel não expõe.
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, 'tblpricing', array_keys($values)),
            $values
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblpricing ($names) VALUES ($placeholders)");

        return $stmt->execute($values);
    }

    // ─── The groups ─────────────────────────────────────────────────────────

    public function productGroups(): array
    {
        $stmt = $this->pdo->query(
            'SELECT g.*, (SELECT COUNT(*) FROM tblproducts p WHERE p.gid = g.id) AS products '
            . 'FROM tblproductgroups g ORDER BY g.`order`, g.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function serverGroups(): array
    {
        $stmt = $this->pdo->query(
            'SELECT g.*, (SELECT COUNT(*) FROM tblservergroupsrel r WHERE r.groupid = g.id) AS servers '
            . 'FROM tblservergroups g ORDER BY g.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveProductGroup(array $data, int $id = 0): int
    {
        $columns = [
            'name' => (string)($data['name'] ?? ''),
            'hidden' => (string)($data['hidden'] ?? ''),
            'order' => (int)($data['order'] ?? 0),
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($columns)));
            $columns['id'] = $id;

            $stmt = $this->pdo->prepare("UPDATE tblproductgroups SET $set WHERE id = :id");
            $stmt->execute($columns);

            return $id;
        }

        // As colunas que a tabela exige e que o painel não expõe.
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, 'tblproductgroups', array_keys($columns)),
            $columns
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblproductgroups ($names) VALUES ($placeholders)");
        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }

    public function saveServerGroup(array $data, int $id = 0): int
    {
        $columns = [
            'name' => (string)($data['name'] ?? ''),
            // `filltype` é como o grupo se preenche: 0 = à mão, 1 = pelos
            // campos do servidor.
            'filltype' => (int)($data['filltype'] ?? 0),
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($columns)));
            $columns['id'] = $id;

            $stmt = $this->pdo->prepare("UPDATE tblservergroups SET $set WHERE id = :id");
            $stmt->execute($columns);

            return $id;
        }

        // As colunas que a tabela exige e que o painel não expõe.
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, 'tblservergroups', array_keys($columns)),
            $columns
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblservergroups ($names) VALUES ($placeholders)");
        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }

    // ─── The servers ────────────────────────────────────────────────────────

    public function servers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM tblservers ORDER BY name'
        );

        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($servers as $index => $server) {
            $servers[$index]['groups'] = $this->groupsOfServer((int)$server['id']);
        }

        return $servers;
    }

    public function server(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tblservers WHERE id = ?');
        $stmt->execute([$id]);

        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($server === false) {
            return null;
        }

        $server['groups'] = $this->groupsOfServer($id);

        return $server;
    }

    public function saveServer(array $data, int $id = 0): int
    {
        $columns = [
            'name' => (string)($data['name'] ?? ''),
            'hostname' => (string)($data['hostname'] ?? ''),
            'ipaddress' => (string)($data['ipaddress'] ?? ''),
            'username' => (string)($data['username'] ?? ''),
            'password' => (string)($data['password'] ?? ''),
            'monthlycost' => (float)($data['monthlycost'] ?? 0),
            'statusaddress' => (string)($data['statusaddress'] ?? ''),
            'maxaccounts' => (int)($data['maxaccounts'] ?? 0),
            'nameserver1' => (string)($data['nameserver1'] ?? ''),
            'nameserver2' => (string)($data['nameserver2'] ?? ''),
            'active' => (int)($data['active'] ?? 1),
            'disabled' => (int)($data['disabled'] ?? 0),
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn (string $c): string => "`$c` = :$c", array_keys($columns)));
            $columns['id'] = $id;

            $stmt = $this->pdo->prepare("UPDATE tblservers SET $set WHERE id = :id");
            $stmt->execute($columns);

            return $id;
        }

        // As colunas que a tabela exige e que o painel não expõe.
        $values = array_merge(
            SchemaDefaults::neutral($this->pdo, 'tblservers', array_keys($columns)),
            $columns
        );

        $names = implode(', ', array_map(fn (string $c): string => "`$c`", array_keys($values)));
        $placeholders = ':' . implode(', :', array_keys($values));

        $stmt = $this->pdo->prepare("INSERT INTO tblservers ($names) VALUES ($placeholders)");
        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }

    public function serversOfGroup(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.* FROM tblservers s '
            . 'INNER JOIN tblservergroupsrel r ON r.serverid = s.id '
            . 'WHERE r.groupid = ? ORDER BY s.name'
        );
        $stmt->execute([$groupId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setServerInGroup(int $groupId, int $serverId, bool $member): bool
    {
        $existing = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tblservergroupsrel WHERE groupid = ? AND serverid = ?'
        );
        $existing->execute([$groupId, $serverId]);

        $present = ((int)$existing->fetchColumn()) > 0;

        if ($member && !$present) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tblservergroupsrel (groupid, serverid) VALUES (?, ?)'
            );

            return $stmt->execute([$groupId, $serverId]);
        }

        if (!$member && $present) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM tblservergroupsrel WHERE groupid = ? AND serverid = ?'
            );

            return $stmt->execute([$groupId, $serverId]);
        }

        return true;
    }

    public function groupsOfServer(int $serverId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT groupid FROM tblservergroupsrel WHERE serverid = ?'
        );
        $stmt->execute([$serverId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function deleteProduct(int $id): bool
    {
        // Os preços do produto, primeiro: ficariam órfãos.
        $prices = $this->pdo->prepare(
            "DELETE FROM tblpricing WHERE type = 'product' AND relid = ?"
        );
        $prices->execute([$id]);

        $stmt = $this->pdo->prepare('DELETE FROM tblproducts WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function servicesOfProduct(int $productId): int
    {
        // Um serviço é uma linha de `tblhosting` deste produto — e a
        // coluna chama-se `packageid`, como no WHMCS.
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tblhosting WHERE packageid = ?'
        );
        $stmt->execute([$productId]);

        return (int)$stmt->fetchColumn();
    }

    public function deleteServer(int $id): bool
    {
        // As ligações aos grupos, primeiro: ficariam órfãs.
        $relations = $this->pdo->prepare(
            'DELETE FROM tblservergroupsrel WHERE serverid = ?'
        );
        $relations->execute([$id]);

        $stmt = $this->pdo->prepare('DELETE FROM tblservers WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
