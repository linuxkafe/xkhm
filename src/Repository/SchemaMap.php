<?php

declare(strict_types=1);

namespace Xkhm\Repository;

/**
 * Maps between the application's canonical field names and the column names
 * of the **real** WHMCS 5.3.9 schema.
 *
 * ## Why this exists
 *
 * The production database is WHMCS 5.3.9. Several of its columns were
 * renamed — or removed altogether — in later WHMCS releases:
 * `tblinvoices.datecreated` became `date`, `tbltickets.subject` became
 * `title`, `tblknowledgebase.content` became `article`, and columns such as
 * `tbltickets.deptid` and `tblknowledgebase.description` simply do not
 * exist in 5.3.9. A repository that hard-codes either set of names is
 * wrong for one of them.
 *
 * Rather than scatter the difference across every repository, service and
 * controller, the difference lives **here**, once. The application speaks
 * canonical names; this class translates.
 *
 * ## The kinds of difference
 *
 * - **rename** — `datecreated` is stored as `date`
 * - **drop** — `deptid` does not exist in `tbltickets` in 5.3.9
 * - **synthesise** — `fullname` is `firstname` + `lastname`
 * - **invert** — the knowledge base's `private` flag is the opposite of
 *   the canonical `published`
 */
final class SchemaMap
{
    /**
     * Canonical column => real column.
     *
     * @var array<string, array<string, string>>
     */
    public const RENAMES = [
        'tblclients' => [
            'clientgroupid' => 'groupid',
            'phone' => 'phonenumber',
        ],
        'tblorders' => [
            'total' => 'amount',
        ],
        'tblinvoices' => [
            'datecreated' => 'date',
            'note' => 'notes',
        ],
        'tbldomains' => [
            'period' => 'registrationperiod',
        ],
        'tbltickets' => [
            'subject' => 'title',
            'priority' => 'urgency',
        ],
        'tblticketreplies' => [
            'adminreply' => 'admin',
        ],
        'tblknowledgebase' => [
            'content' => 'article',
            'catid' => 'parentid',
        ],
        'tblknowledgebasecats' => [
            'title' => 'name',
        ],
        'tblannouncements' => [
            'message' => 'announcement',
        ],
        'tblinvoiceitems' => [
            'note' => 'notes',
        ],
    ];

    /**
     * Canonical columns that are **inverted** on the real schema.
     *
     * The knowledge base's visibility flag is `private` in WHMCS 5.3.9 and
     * `published` in the canonical model.
     *
     * @var array<string, array<string, string>>
     */
    public const INVERTED = [
        'tblknowledgebase' => [
            'published' => 'private',
        ],
    ];

    /**
     * Canonical columns that **do not exist** in the real schema.
     *
     * Their value is dropped on write and synthesised on read.
     *
     * @var array<string, array<string, mixed>>
     */
    public const SYNTHESISED = [
        'tbladmins' => [
            // The admin's display name is the two name columns.
            'fullname' => ['firstname', 'lastname'],
        ],
        'tblknowledgebase' => [
            // WHMCS 5.3.9 has neither a description nor a timestamp.
            'description' => '',
            'lastupdated' => '',
        ],
        'tblknowledgebasecats' => [
            // The category has no sort order; `catid` is the parent's id.
            'sortorder' => 0,
        ],
        'tbltickets' => [
            // WHMCS 5.3.9 stores no department on the ticket itself.
            'deptid' => 0,
        ],
        'tblticketreplies' => [
            // No attachment column in 5.3.9.
            'attachment' => null,
        ],
        'tbldomainpricing' => [
            // WHMCS 5.3.9 keeps the prices in `tblpricing`; the extension
            // table carries only the flags.
            'msetupprice' => 0.0,
            'renprice' => 0.0,
        ],
        'tblaffiliates' => [
            // The WHMCS 6+ programme model does not exist in 5.3.9.
            'name' => '',
            'email' => '',
            'refcode' => '',
            'commission' => 0.0,
            'unpaidbalance' => 0.0,
            'userid' => null,
        ],
        'tblorders' => [
            // WHMCS 5.3.9 has no tax, gateway or currency on the order.
            'tax' => 0.0,
            'gateway' => '',
            'currency' => 1,
        ],
        'tblinvoices' => [
            'currency' => 1,
        ],
        'tbldomains' => [
            // No DNS nameservers, no currency and no insert timestamp.
            'dns1' => null,
            'dns2' => null,
            'currency' => 1,
            'dateadded' => null,
        ],
        'tblclients' => [
            'authmodule' => '',
            'authdata' => '',
        ],
    ];

    /**
     * The columns the real schema requires on an INSERT, with their
     * defaults.
     *
     * WHMCS 5.3.9 declares almost every column as `NOT NULL` **without a
     * default**, so an INSERT that names only the interesting columns is
     * rejected. These defaults fill the rest.
     *
     * @var array<string, array<string, mixed>>
     */
    private const REQUIRED_DEFAULTS = [
        'tblclients' => [
            'firstname' => '', 'lastname' => '', 'companyname' => '',
            'email' => '', 'address1' => '', 'address2' => '', 'city' => '',
            'state' => '', 'postcode' => '', 'country' => '',
            'phonenumber' => '', 'password' => '', 'authmodule' => '',
            'authdata' => '', 'currency' => 0, 'defaultgateway' => '',
            'credit' => 0.0, 'taxexempt' => '', 'latefeeoveride' => '',
            'overideduenotices' => '', 'separateinvoices' => '',
            'disableautocc' => '', 'datecreated' => '@date', 'notes' => '',
            'billingcid' => 0, 'securityqid' => 0, 'securityqans' => '',
            'groupid' => 0, 'cardlastfour' => '', 'cardnum' => '',
            'startdate' => '', 'expdate' => '', 'issuenumber' => '',
            'bankname' => '', 'banktype' => '', 'bankcode' => '',
            'bankacct' => '', 'gatewayid' => '', 'ip' => '', 'host' => '',
            'language' => '', 'pwresetkey' => '', 'pwresetexpiry' => 0,
            'emailoptout' => 0, 'overrideautoclose' => 0,
        ],
        'tbladmins' => [
            'roleid' => 0, 'username' => '', 'authmodule' => '',
            'authdata' => '', 'firstname' => '', 'lastname' => '',
            'email' => '', 'signature' => '', 'notes' => '', 'template' => '',
            'language' => '', 'disabled' => 0, 'loginattempts' => 0,
            'supportdepts' => '', 'ticketnotifications' => '',
            'homewidgets' => '',
        ],
        'tblorders' => [
            'ordernum' => 0, 'userid' => 0, 'contactid' => 0, 'date' => '@datetime',
            'nameservers' => '', 'transfersecret' => '', 'renewals' => '',
            'promocode' => '', 'promotype' => '', 'promovalue' => '',
            'orderdata' => '', 'amount' => 0.0, 'paymentmethod' => '',
            'status' => '', 'ipaddress' => '', 'fraudmodule' => '',
            'fraudoutput' => '', 'notes' => '',
        ],
        'tblinvoices' => [
            'userid' => 0, 'invoicenum' => '', 'subtotal' => 0.0,
            'credit' => 0.0, 'tax' => 0.0, 'tax2' => 0.0, 'taxrate' => 0.0,
            'taxrate2' => 0.0, 'status' => '', 'paymentmethod' => '',
            'notes' => '',
        ],
        'tblinvoiceitems' => [
            'userid' => 0, 'type' => '', 'relid' => 0, 'description' => '',
            'taxed' => 0, 'paymentmethod' => '', 'notes' => '',
        ],
        'tbldomains' => [
            'userid' => 0, 'orderid' => 0, 'type' => 'Register',
            'registrationdate' => '@date', 'domain' => '', 'recurringamount' => 0.0,
            'registrar' => '', 'subscriptionid' => '', 'promoid' => 0,
            'nextinvoicedate' => '@date', 'additionalnotes' => '',
            'paymentmethod' => '', 'dnsmanagement' => '', 'emailforwarding' => '',
            'idprotection' => '', 'donotrenew' => '', 'reminders' => '',
            'synced' => 0,
        ],
        'tbltickets' => [
            'tid' => '', 'did' => 0, 'userid' => 0, 'contactid' => 0,
            'name' => '', 'email' => '', 'cc' => '', 'c' => '', 'title' => '',
            'message' => '', 'status' => '', 'urgency' => '', 'admin' => '',
            'attachment' => '', 'lastreply' => '@datetime', 'flag' => 0,
            'clientunread' => 0, 'adminunread' => '', 'replyingadmin' => 0,
            'replyingtime' => '@datetime', 'service' => '',
        ],
        'tblticketreplies' => [
            'tid' => 0, 'userid' => 0, 'contactid' => 0, 'name' => '',
            'email' => '', 'date' => '@datetime', 'message' => '', 'admin' => '',
            'attachment' => '', 'rating' => 0,
        ],
        'tblknowledgebase' => [
            'title' => '', 'article' => '', 'private' => '', 'order' => 0,
            'parentid' => 0, 'language' => '',
        ],
        'tblknowledgebasecats' => [
            'name' => '', 'description' => '', 'hidden' => '', 'catid' => 0,
            'language' => '',
        ],
        'tblannouncements' => [
            'title' => '', 'announcement' => '', 'published' => '',
            'parentid' => 0, 'language' => '',
        ],
        'tblaffiliates' => [
            'clientid' => 0, 'visitors' => 0, 'paytype' => '',
            'payamount' => 0.0, 'onetime' => 0,
        ],
        'tblaffiliateshistory' => [
            'affiliateid' => 0, 'date' => '@datetime', 'affaccid' => 0,
            'description' => '', 'amount' => 0.0,
        ],
    ];

    /**
     * The required defaults of a table, ready to be used in an insert.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(string $table): array
    {
        return self::requiredDefaults($table);
    }

    /**
     * The required defaults of a table, with the dates resolved.
     *
     * The date columns cannot be a constant: PHP constants hold no runtime
     * expressions.
     *
     * @return array<string, mixed>
     */
    public static function requiredDefaults(string $table): array
    {
        $defaults = self::REQUIRED_DEFAULTS[$table] ?? [];

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        foreach ($defaults as $column => $value) {
            if ($value === '@date') {
                $defaults[$column] = $today;
            } elseif ($value === '@datetime') {
                $defaults[$column] = $now;
            }
        }

        return $defaults;
    }

    /**
     * Complete a canonical row with the columns the real schema requires.
     *
     * The caller's values win; the defaults only fill what is missing.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function withRequiredDefaults(string $table, array $data): array
    {
        $defaults = self::requiredDefaults($table);

        // The defaults are in *real* column names; the canonical row has to
        // be translated first so a rename does not leave the default behind.
        $real = self::toReal($table, $data);

        foreach ($defaults as $column => $default) {
            if (!array_key_exists($column, $real)) {
                $real[$column] = $default;
            }
        }

        return $real;
    }

    /**
     * Columns that hold a boolean as **text** in the real schema.
     *
     * WHMCS 5.3.9 writes `'1'`/`'0'` in some tables and `'on'`/`'off'` in
     * others. The canonical model uses 1/0, so the values are normalised in
     * both directions.
     *
     * @var array<string, list<string>>
     */
    public const BOOLEAN_TEXT = [
        'tblannouncements' => ['published'],
        'tblknowledgebase' => ['private'],
    ];

    /**
     * The text values that mean "true" in the real schema.
     */
    private const TRUTHY = ['1', 'on', 'yes', 'true', 'y', 't'];

    /**
     * Is the given stored value a "true" boolean?
     */
    public static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), self::TRUTHY, true);
    }

    /**
     * The canonical columns of a table — the ones the application reads.
     *
     * @return list<string>
     */
    public static function canonicalColumns(string $table): array
    {
        return match ($table) {
            'tblclients' => [
                'id', 'firstname', 'lastname', 'companyname', 'email',
                'password', 'status', 'credit', 'currency', 'datecreated',
                'clientgroupid', 'notes',
            ],
            'tbladmins' => [
                'id', 'username', 'password', 'email', 'firstname', 'lastname',
                'fullname', 'disabled', 'language', 'notes',
            ],
            'tblorders' => [
                'id', 'userid', 'date', 'status', 'paymentmethod', 'notes',
                'total', 'tax', 'gateway', 'currency',
            ],
            'tblinvoices' => [
                'id', 'userid', 'datecreated', 'duedate', 'datepaid', 'status',
                'total', 'taxrate', 'note', 'currency',
            ],
            'tbldomains' => [
                'id', 'userid', 'orderid', 'domain', 'period', 'registrationdate',
                'expirydate', 'nextduedate', 'status', 'registrar', 'type',
            ],
            'tbltickets' => [
                'id', 'userid', 'name', 'email', 'subject', 'message', 'status',
                'priority', 'deptid', 'date', 'lastreply',
            ],
            'tblticketreplies' => [
                'id', 'tid', 'userid', 'name', 'email', 'message', 'date',
                'adminreply',
            ],
            'tblknowledgebase' => [
                'id', 'catid', 'title', 'description', 'content', 'published',
                'lastupdated',
            ],
            'tblknowledgebasecats' => [
                'id', 'title', 'description', 'sortorder',
            ],
            'tblannouncements' => [
                'id', 'title', 'message', 'published', 'date',
            ],
            'tblinvoiceitems' => [
                'id', 'invoiceid', 'description', 'amount', 'taxed',
            ],
            'tbldomainpricing' => [
                'extension', 'msetupprice', 'renprice',
            ],
            'tblaffiliates' => [
                'id', 'clientid', 'visitors', 'paytype', 'payamount', 'onetime',
                'balance', 'withdrawn',
            ],
            'tblticketdepartments' => [
                'id', 'name', 'description', 'email',
            ],
            default => [],
        };
    }

    /**
     * The real column name for a canonical one, or null when the column
     * does not exist in the real schema.
     */
    public static function realColumn(string $table, string $canonical): ?string
    {
        $renames = self::RENAMES[$table] ?? [];

        if (isset($renames[$canonical])) {
            return $renames[$canonical];
        }

        // A synthesised column has no real counterpart.
        if (array_key_exists($canonical, self::SYNTHESISED[$table] ?? [])) {
            return null;
        }

        return $canonical;
    }

    /**
     * Translate a canonical row into the real column names.
     *
     * Columns that do not exist in the real schema are dropped; the
     * inverted ones are flipped.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function toReal(string $table, array $data): array
    {
        $renames = self::RENAMES[$table] ?? [];
        $inverted = self::INVERTED[$table] ?? [];
        $synthesised = self::SYNTHESISED[$table] ?? [];
        $booleanText = self::BOOLEAN_TEXT[$table] ?? [];

        $out = [];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $synthesised)) {
                // No real column: the value is dropped on write.
                continue;
            }

            if (isset($inverted[$key])) {
                $out[$inverted[$key]] = (int)$value === 1 ? 0 : 1;
                continue;
            }

            $real = $renames[$key] ?? $key;

            // The boolean-as-text columns are written as 'on'/'off'.
            if (in_array($real, $booleanText, true)) {
                $out[$real] = self::isTruthy($value) ? 'on' : 'off';
                continue;
            }

            $out[$real] = $value;
        }

        return $out;
    }

    /**
     * Translate a real row into the canonical names.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function toCanonical(string $table, array $row): array
    {
        $renames = self::RENAMES[$table] ?? [];
        $inverted = self::INVERTED[$table] ?? [];
        $synthesised = self::SYNTHESISED[$table] ?? [];

        // Real name => canonical name.
        $reverse = array_flip($renames);

        $out = [];
        foreach ($row as $key => $value) {
            $canonical = $reverse[$key] ?? $key;
            $out[$canonical] = $value;
        }

        // The inverted flags.
        foreach ($inverted as $canonical => $real) {
            if (array_key_exists($real, $row)) {
                $out[$canonical] = self::isTruthy($row[$real]) ? 0 : 1;
            }
        }

        // The boolean-as-text columns.
        foreach (self::BOOLEAN_TEXT[$table] ?? [] as $column) {
            if (array_key_exists($column, $row)) {
                $out[$column] = self::isTruthy($row[$column]) ? 1 : 0;
            }
        }

        // The synthesised columns.
        foreach ($synthesised as $canonical => $source) {
            if (is_array($source)) {
                $parts = [];
                foreach ($source as $field) {
                    $value = $out[$field] ?? '';
                    if ($value !== '' && $value !== null) {
                        $parts[] = (string)$value;
                    }
                }
                $out[$canonical] = implode(' ', $parts);
            } else {
                $out[$canonical] = $source;
            }
        }

        return $out;
    }
}
