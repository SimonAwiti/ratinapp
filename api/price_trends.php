<?php
/**
 * price_trends.php — PROPOSED public endpoint (NEW)
 * ─────────────────────────────────────────────────────────────
 * mode=catalog  → dropdown data (countries, markets, commodities) for all
 *                 three chart components. One small cached call, reused
 *                 everywhere — keeps the public pages fast the same way
 *                 marketprices_dashboard.php pre-populates its filters.
 *
 * mode=single   → Wholesale + Retail series for ONE filter combo
 *                 (country/market/commodity), grouped by date. Adapted
 *                 directly from the `chart_data` block in
 *                 marketprices_dashboard.php. Powers:
 *                   - Homepage → Commodity Prices card   (Screenshot 1)
 *                   - Commodities tab → Price Trends      (Screenshot 2)
 *                 Hard-capped at 90 days (3 months) for both — the
 *                 frontend already never asks for more, but this is the
 *                 real enforcement point.
 *
 * mode=compare  → up to 4 commodity(+country) series side by side.
 *                 Powers Homepage → Processor Prices Overview compare
 *                 (item #2: e.g. Beans (Red) Kenya vs Tanzania, or Maize
 *                 vs Beans within Kenya). Capped at 270 days (9 months).
 *                 Accepts either:
 *                   ids=Maize,Beans          (+ optional single `country`
 *                                               applied to all series)
 *                   pairs=Maize|Kenya,Beans (Red)|Tanzania
 *                                             (per-series country, comma
 *                                              separated, pipe-delimited)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../admin/includes/config.php'; // reuse existing mysqli $con, same style as processor_prices_detailed.php

if (!isset($con) || !($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => 'Database connection not available']);
    exit;
}

const SINGLE_MAX_DAYS  = 90;  // 3 months — Screenshot 1 & 2 visitor cap
const COMPARE_MAX_DAYS = 270; // 9 months — Processor Prices compare cap

$mode = in_array($_GET['mode'] ?? '', ['catalog', 'single', 'compare']) ? $_GET['mode'] : 'single';

// ─────────────────────────────────────────────────────────────
// mode=catalog
// ─────────────────────────────────────────────────────────────
if ($mode === 'catalog') {
    $filterCountry = trim($_GET['country'] ?? '');

    try {
        // Bounded to the last 2 years so this can't turn into a full-table
        // scan as market_prices grows — dropdowns don't need commodities/
        // markets that haven't posted a price in years anyway.

        // Countries: always the FULL list, regardless of $filterCountry —
        // this populates the Country dropdown itself.
        $countries = [];
        $r = $con->query("SELECT DISTINCT country_admin_0 FROM market_prices
                           WHERE status='published' AND country_admin_0 != ''
                             AND date_posted >= DATE_SUB(NOW(), INTERVAL 730 DAY)
                           ORDER BY country_admin_0");
        if ($r === false) throw new Exception('countries query failed: ' . $con->error);
        while ($row = $r->fetch_assoc()) $countries[] = $row['country_admin_0'];

        // Markets: scoped to $filterCountry when supplied.
        $markets = [];
        $marketSql = "SELECT mp.market_id, mp.market,
                              MAX(NULLIF(mp.country_admin_0, '')) AS country_admin_0
                       FROM market_prices mp
                       WHERE mp.status='published'
                         AND mp.date_posted >= DATE_SUB(NOW(), INTERVAL 730 DAY)";
        $marketParams = [];
        $marketTypes = '';
        if ($filterCountry !== '') {
            $marketSql .= " AND mp.country_admin_0 = ?";
            $marketParams[] = $filterCountry;
            $marketTypes .= 's';
        }
        $marketSql .= " GROUP BY mp.market_id, mp.market ORDER BY country_admin_0, mp.market";
        $stmt = $con->prepare($marketSql);
        if ($stmt === false) throw new Exception('markets prepare failed: ' . $con->error);
        if ($marketParams) $stmt->bind_param($marketTypes, ...$marketParams);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $markets[] = ['id' => $row['market_id'], 'name' => $row['market'], 'country' => $row['country_admin_0'] ?? ''];
        }
        $stmt->close();

        // Commodities: scoped to $filterCountry when supplied — only
        // commodities with an actual published record in that country.
        $commodities = [];
        $commoditySql = "SELECT DISTINCT c.id,
                                 CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS label,
                                 c.commodity_name, c.variety
                          FROM commodities c
                          INNER JOIN market_prices mp ON mp.commodity = c.id
                          WHERE mp.status = 'published'
                            AND mp.date_posted >= DATE_SUB(NOW(), INTERVAL 730 DAY)";
        $commodityParams = [];
        $commodityTypes = '';
        if ($filterCountry !== '') {
            $commoditySql .= " AND mp.country_admin_0 = ?";
            $commodityParams[] = $filterCountry;
            $commodityTypes .= 's';
        }
        $commoditySql .= " ORDER BY c.commodity_name, c.variety";
        $stmt = $con->prepare($commoditySql);
        if ($stmt === false) throw new Exception('commodities prepare failed: ' . $con->error);
        if ($commodityParams) $stmt->bind_param($commodityTypes, ...$commodityParams);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $commodities[] = ['id' => $row['id'], 'label' => $row['label'], 'name' => $row['commodity_name']];
        }
        $stmt->close();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'msg' => 'Catalog query failed: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => true, 'countries' => $countries, 'markets' => $markets, 'commodities' => $commodities]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// mode=single — Wholesale + Retail series for one filter combo
// ─────────────────────────────────────────────────────────────
if ($mode === 'single') {
    $country    = trim($_GET['country'] ?? '');
    $market_id  = (int)($_GET['market_id'] ?? 0);
    $commodity  = trim($_GET['commodity'] ?? ''); // numeric id or commodity name
    $days       = min((int)($_GET['days'] ?? 30), SINGLE_MAX_DAYS);

    $where  = ["mp.status = 'published'", "mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
    $params = [$days];
    $types  = 'i';

    if ($country !== '') {
        $where[] = "mp.country_admin_0 = ?";
        $params[] = $country;
        $types .= 's';
    }
    if ($market_id) {
        $where[] = "mp.market_id = ?";
        $params[] = $market_id;
        $types .= 'i';
    }
    if ($commodity !== '') {
        if (ctype_digit($commodity)) {
            $where[] = "mp.commodity = ?";
            $params[] = (int)$commodity;
            $types .= 'i';
        } else {
            $where[] = "c.commodity_name = ?";
            $params[] = $commodity;
            $types .= 's';
        }
    }

    $sql = "SELECT DATE(mp.date_posted) AS date_label,
                   mp.price_type,
                   AVG(mp.Price) AS avg_price,
                   MIN(mp.Price) AS min_price,
                   MAX(mp.Price) AS max_price,
                   COUNT(*) AS record_count
            FROM market_prices mp
            LEFT JOIN commodities c ON mp.commodity = c.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY DATE(mp.date_posted), mp.price_type
            ORDER BY date_label ASC";

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'msg' => 'Query prepare failed: ' . $con->error]);
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// mode=compare — up to 4 series (commodity, optionally per-series country)
// ─────────────────────────────────────────────────────────────
if ($mode === 'compare') {
    $price_type = in_array($_GET['price_type'] ?? '', ['Wholesale', 'Retail']) ? $_GET['price_type'] : 'Wholesale';
    $days       = min((int)($_GET['days'] ?? COMPARE_MAX_DAYS), COMPARE_MAX_DAYS);
    $series     = [];

    $pairsRaw = trim($_GET['pairs'] ?? '');
    if ($pairsRaw !== '') {
        // pairs mode: "12|Kenya,45|Tanzania" (commodity id, preferred) or
        // "Maize|Kenya" (commodity name, legacy fallback) — per-series country
        $pairList = array_slice(array_filter(array_map('trim', explode(',', $pairsRaw))), 0, 4);
        foreach ($pairList as $pair) {
            [$commodityRaw, $countryName] = array_pad(explode('|', $pair, 2), 2, '');
            $commodityRaw = trim($commodityRaw);
            $countryName  = trim($countryName);
            if ($commodityRaw === '') continue;

            $isNumeric = ctype_digit($commodityRaw);
            $where  = ["mp.status = 'published'", "mp.price_type = ?", "mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
            $params = [$price_type, $days];
            $types  = 'si';
            if ($isNumeric) {
                $where[] = "mp.commodity = ?";
                $params[] = (int)$commodityRaw;
                $types .= 'i';
            } else {
                $where[] = "c.commodity_name = ?";
                $params[] = $commodityRaw;
                $types .= 's';
            }
            if ($countryName !== '') {
                $where[] = "mp.country_admin_0 = ?";
                $params[] = $countryName;
                $types .= 's';
            }

            $sql = "SELECT DATE(mp.date_posted) AS d, AVG(mp.Price) AS avg_price,
                           CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS base_label
                    FROM market_prices mp
                    LEFT JOIN commodities c ON mp.commodity = c.id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY DATE(mp.date_posted), base_label
                    ORDER BY d ASC";

            $stmt = $con->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['success' => false, 'msg' => 'Query prepare failed: ' . $con->error]);
                exit;
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $points = [];
            $baseLabel = $commodityRaw;
            while ($row = $res->fetch_assoc()) {
                $points[] = ['date' => $row['d'], 'avg_price' => (float)$row['avg_price']];
                $baseLabel = $row['base_label'];
            }
            $stmt->close();
            if (!empty($points)) {
                $label = $countryName !== '' ? "$baseLabel — $countryName" : $baseLabel;
                $series[] = ['id' => $pair, 'label' => $label, 'points' => $points];
            }
        }
    } else {
        // ids mode: "Maize,Beans" (+ optional shared `country`)
        $idsRaw = trim($_GET['ids'] ?? '');
        $country = trim($_GET['country'] ?? '');
        if ($idsRaw === '') {
            echo json_encode(['success' => false, 'msg' => 'No ids or pairs supplied']);
            exit;
        }
        $idList = array_slice(array_filter(array_map('trim', explode(',', $idsRaw))), 0, 4);

        foreach ($idList as $rawId) {
            $isNumeric = ctype_digit($rawId);
            $where  = ["mp.status = 'published'", "mp.price_type = ?", "mp.date_posted >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
            $params = [$price_type, $days];
            $types  = 'si';

            if ($isNumeric) {
                $where[] = "mp.commodity = ?";
                $params[] = (int)$rawId;
                $types .= 'i';
            } else {
                $where[] = "c.commodity_name = ?";
                $params[] = $rawId;
                $types .= 's';
            }
            if ($country !== '') {
                $where[] = "mp.country_admin_0 = ?";
                $params[] = $country;
                $types .= 's';
            }

            $sql = "SELECT DATE(mp.date_posted) AS d, AVG(mp.Price) AS avg_price,
                           CONCAT(c.commodity_name, IF(c.variety IS NOT NULL AND c.variety != '', CONCAT(' (', c.variety, ')'), '')) AS label
                    FROM market_prices mp
                    LEFT JOIN commodities c ON mp.commodity = c.id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY DATE(mp.date_posted), label
                    ORDER BY d ASC";

            $stmt = $con->prepare($sql);
            if ($stmt === false) {
                echo json_encode(['success' => false, 'msg' => 'Query prepare failed: ' . $con->error]);
                exit;
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $points = [];
            $label = $rawId;
            while ($row = $res->fetch_assoc()) {
                $points[] = ['date' => $row['d'], 'avg_price' => (float)$row['avg_price']];
                $label = $row['label'];
            }
            $stmt->close();
            if (!empty($points)) {
                $series[] = ['id' => $rawId, 'label' => $label, 'points' => $points];
            }
        }
    }

    echo json_encode(['success' => true, 'series' => $series]);
    exit;
}
