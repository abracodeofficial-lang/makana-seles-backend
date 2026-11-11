<?php
/**
 * Plugin Name: Makana Connector
 * Description: ربط نظام مكانة بالموقع — بيستقبل العقارات من النظام وبينشرها كإعلانات MyListing.
 * Version: 1.0.0
 * Author: Makana
 * Requires PHP: 7.4
 * Text Domain: makana-connector
 */

if (!defined('ABSPATH')) exit;

final class Makana_Connector
{
    const VERSION   = '1.0.0';
    const NS        = 'makana/v1';
    const POST_TYPE = 'job_listing';
    const OPTION    = 'makana_connector';

    // كلمات بعناوين حقول القالب → مفتاح من بيانات النظام (الأطول/الأدق أول)
    const FIELD_HINTS = [
        'ad_license_expiry'  => ['انتهاء الترخيص', 'انتهاء رخصة', 'تاريخ انتهاء'],
        'fal_license_number' => ['رخصة فال', 'رقم فال', 'فال'],
        'ad_license_number'  => ['ترخيص الإعلان', 'رخصة الإعلان', 'رقم الترخيص', 'الترخيص'],
        'street_width'       => ['عرض الشارع', 'الشارع'],
        'price'              => ['السعر', 'سعر العقار', 'سعر'],
        'area'               => ['المساحة', 'مساحة'],
        'rooms'              => ['عدد الغرف', 'الغرف', 'غرف'],
        'bathrooms'          => ['دورات المياه', 'دورات', 'الحمامات', 'حمام'],
        'halls'              => ['الصالات', 'صالات', 'صالة'],
        'floors'             => ['عدد الأدوار', 'عدد الطوابق', 'الطوابق'],
        'facade'             => ['الواجهة', 'واجهة'],
        'direction'          => ['الاتجاه', 'اتجاه'],
        'purpose'            => ['نوع العرض', 'الغرض', 'نوع الإعلان'],
        'code'               => ['رقم العقار', 'كود العقار', 'الرقم المرجعي', 'الكود'],
        'neighborhood'       => ['الحي', 'حي'],
        'city'               => ['المدينة', 'مدينة'],
    ];

    const DEFAULT_TYPE_MAP = "شقة=apartment\nفيلا=villas\nدور=floors\nأرض=lands";

    public static function boot(): void
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    // ── الإعدادات ─────────────────────────────────────────────
    public static function opts(): array
    {
        return wp_parse_args(get_option(self::OPTION, []), [
            'type_map'     => self::DEFAULT_TYPE_MAP,
            'default_type' => '',
            'field_map'    => '',
            'post_status'  => 'publish',
        ]);
    }

    public static function register_settings(): void
    {
        register_setting('makana_connector', self::OPTION, [
            'sanitize_callback' => function ($v) {
                $v = is_array($v) ? $v : [];
                return [
                    'type_map'     => sanitize_textarea_field($v['type_map'] ?? ''),
                    'default_type' => sanitize_key($v['default_type'] ?? ''),
                    'field_map'    => sanitize_textarea_field($v['field_map'] ?? ''),
                    'post_status'  => in_array($v['post_status'] ?? '', ['publish', 'pending', 'draft'], true) ? $v['post_status'] : 'publish',
                ];
            },
        ]);
    }

    // ── REST ──────────────────────────────────────────────────
    public static function routes(): void
    {
        $auth = function () { return current_user_can('edit_others_posts'); };

        register_rest_route(self::NS, '/ping', [
            'methods' => 'GET', 'permission_callback' => $auth,
            'callback' => function () {
                $types = self::listing_types();
                return [
                    'ok'        => true,
                    'version'   => self::VERSION,
                    'site'      => get_bloginfo('name'),
                    'mylisting' => post_type_exists(self::POST_TYPE),
                    'user'      => wp_get_current_user()->user_login,
                    'type_map'  => self::type_map(),
                    'types'     => array_map(function ($t) {
                        return ['slug' => $t['slug'], 'title' => $t['title'], 'mapped' => self::auto_field_map($t['fields'])];
                    }, $types),
                ];
            },
        ]);

        register_rest_route(self::NS, '/listings', [
            'methods' => 'POST', 'permission_callback' => $auth,
            'callback' => function (WP_REST_Request $r) { return self::save($r->get_json_params() ?: [], 0); },
        ]);

        register_rest_route(self::NS, '/listings/(?P<id>\d+)', [
            [
                'methods' => 'PUT', 'permission_callback' => $auth,
                'callback' => function (WP_REST_Request $r) { return self::save($r->get_json_params() ?: [], (int) $r['id']); },
            ],
            [
                'methods' => 'DELETE', 'permission_callback' => $auth,
                'callback' => function (WP_REST_Request $r) { return self::hide((int) $r['id']); },
            ],
        ]);
    }

    // ── أنواع الإعلانات وحقولها (من إعدادات MyListing) ─────────
    public static function listing_types(): array
    {
        $posts = get_posts(['post_type' => 'case27_listing_type', 'numberposts' => -1, 'post_status' => 'publish']);
        $out = [];
        foreach ($posts as $p) {
            $raw = get_post_meta($p->ID, 'case27_listing_type_fields', true);
            if (is_string($raw)) {
                $dec = json_decode($raw, true);
                $raw = is_array($dec) ? $dec : maybe_unserialize($raw);
            }
            $fields = [];
            foreach ((array) $raw as $k => $f) {
                if (!is_array($f)) continue;
                $slug = $f['slug'] ?? (is_string($k) ? $k : null);
                if (!$slug) continue;
                $fields[] = ['slug' => $slug, 'label' => (string) ($f['label'] ?? $slug), 'type' => (string) ($f['type'] ?? '')];
            }
            $out[] = ['slug' => $p->post_name, 'title' => $p->post_title, 'fields' => $fields];
        }
        return $out;
    }

    public static function type_map(): array
    {
        $map = [];
        foreach (preg_split('/\r\n|\n/', self::opts()['type_map']) as $line) {
            if (strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && $v !== '') $map[$k] = $v;
        }
        return $map;
    }

    // الحقول الأساسية (العنوان/الوصف/الصور/الموقع...) بنعبيها بطريقة خاصة، فما بتنربط تلقائياً
    const RESERVED = ['job_title', 'job_description', 'job_gallery', 'job_cover', 'job_logo', 'job_location', 'job_category', 'job_tags', 'region', 'job_email', 'job_phone', 'job_website'];

    public static function auto_field_map(array $fields): array
    {
        $map  = [];
        $used = [];
        foreach (self::FIELD_HINTS as $key => $hints) {
            foreach ($hints as $hint) {
                foreach ($fields as $f) {
                    if (in_array($f['slug'], self::RESERVED, true) || isset($used[$f['slug']])) continue;
                    if (mb_strpos($f['label'], $hint) !== false) {
                        $map[$key] = $f['slug'];
                        $used[$f['slug']] = true;
                        continue 3;
                    }
                }
            }
        }
        // ربط يدوي من صفحة الإعدادات بيغلب التلقائي: price=job_price
        foreach (preg_split('/\r\n|\n/', self::opts()['field_map']) as $line) {
            if (strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && $v !== '') $map[$k] = $v;
        }
        return $map;
    }

    private static function resolve_type(?string $systemType): array
    {
        $types = self::listing_types();
        $bySlug = [];
        foreach ($types as $t) $bySlug[$t['slug']] = $t;

        $slug = null;
        foreach (self::type_map() as $name => $s) {
            if ($systemType && mb_strpos($systemType, $name) !== false) { $slug = $s; break; }
        }
        if (!$slug || !isset($bySlug[$slug])) $slug = self::opts()['default_type'] ?: null;
        if (!$slug || !isset($bySlug[$slug])) {
            return [null, sprintf('نوع العقار "%s" مش مربوط بأي قسم بالموقع — ضيفه بإعدادات Makana Connector', $systemType ?: '—')];
        }
        return [$bySlug[$slug], null];
    }

    // ── إنشاء/تحديث إعلان ────────────────────────────────────
    private static function save(array $d, int $id)
    {
        if (!post_type_exists(self::POST_TYPE)) {
            return new WP_Error('makana_no_mylisting', 'قالب MyListing مش مفعّل على هالموقع', ['status' => 500]);
        }
        if (empty($d['uuid']) || empty($d['title'])) {
            return new WP_Error('makana_invalid', 'بيانات ناقصة (uuid/title)', ['status' => 422]);
        }

        // نفس العقار ما بينعمل مرتين (لو انعاد الطلب)
        if (!$id) {
            $found = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
                'meta_key' => '_makana_uuid', 'meta_value' => sanitize_text_field($d['uuid'])]);
            if ($found) $id = (int) $found[0];
        } elseif (get_post_type($id) !== self::POST_TYPE) {
            return new WP_Error('makana_not_found', 'الإعلان مش موجود', ['status' => 404]);
        }

        [$type, $err] = self::resolve_type($d['type'] ?? null);
        if ($err) return new WP_Error('makana_type', $err, ['status' => 422]);

        $content = wp_kses_post(($d['description'] ?? '') . (empty($d['features']) ? '' : "\n<h3>المميزات</h3>\n" . $d['features'])
            . (empty($d['components']) ? '' : "\n<h3>المكونات</h3>\n" . $d['components']));

        $post = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => sanitize_text_field($d['title']),
            'post_content' => $content,
            'post_status'  => self::opts()['post_status'],
        ];
        if ($id) { $post['ID'] = $id; $res = wp_update_post($post, true); }
        else     { $post['post_author'] = get_current_user_id(); $res = wp_insert_post($post, true); }
        if (is_wp_error($res)) return $res;
        $id = (int) $res;

        update_post_meta($id, '_makana_uuid', sanitize_text_field($d['uuid']));
        update_post_meta($id, '_makana_code', sanitize_text_field($d['code'] ?? ''));
        update_post_meta($id, '_case27_listing_type', $type['slug']);

        // الحقول المخصصة حسب الربط
        $map = self::auto_field_map($type['fields']);
        foreach ($map as $key => $slug) {
            if (!array_key_exists($key, $d) || $d[$key] === null || $d[$key] === '') continue;
            $val = is_numeric($d[$key]) && (float) $d[$key] == 0 && in_array($key, ['price', 'area', 'rooms', 'bathrooms', 'halls', 'floors', 'street_width'], true) ? '' : $d[$key];
            if ($val === '') continue;
            update_post_meta($id, '_' . ltrim($slug, '_'), is_string($val) ? sanitize_text_field($val) : $val);
        }

        // الموقع
        $address = trim(implode('، ', array_filter([$d['neighborhood'] ?? '', $d['city'] ?? ''])));
        $lat = isset($d['latitude']) && $d['latitude'] !== null ? (float) $d['latitude'] : null;
        $lng = isset($d['longitude']) && $d['longitude'] !== null ? (float) $d['longitude'] : null;
        if ($address) update_post_meta($id, '_job_location', $address);
        if ($lat !== null && $lng !== null) {
            update_post_meta($id, 'geolocation_lat', $lat);
            update_post_meta($id, 'geolocation_long', $lng);
            self::save_location_row($id, $address, $lat, $lng);
        }

        // المنطقة (لو القالب فيه تصنيف region والمدينة موجودة فيه)
        if (!empty($d['city']) && taxonomy_exists('region')) {
            $term = get_term_by('name', $d['city'], 'region');
            if ($term) wp_set_object_terms($id, [(int) $term->term_id], 'region');
        }

        // الصور
        $warnings = [];
        $urls = self::import_images($id, (array) ($d['images'] ?? []), $warnings);
        update_post_meta($id, '_job_gallery', $urls);
        if ($urls) {
            update_post_meta($id, '_job_cover', [$urls[0]]);
            $first = attachment_url_to_postid($urls[0]);
            if ($first) set_post_thumbnail($id, $first);
        }

        clean_post_cache($id);

        return [
            'id'       => $id,
            'url'      => get_permalink($id),
            'type'     => $type['slug'],
            'mapped'   => array_keys($map),
            'warnings' => $warnings,
        ];
    }

    // جدول المواقع تبع MyListing (للبحث على الخريطة) — لو موجود
    private static function save_location_row(int $id, string $address, float $lat, float $lng): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mylisting_locations';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        if (!in_array('listing_id', $cols, true) || !in_array('lat', $cols, true) || !in_array('lng', $cols, true)) return;

        $wpdb->delete($table, ['listing_id' => $id]);
        $row = ['listing_id' => $id, 'lat' => $lat, 'lng' => $lng];
        if (in_array('address', $cols, true)) $row['address'] = $address;
        $wpdb->insert($table, $row);
    }

    // بينزّل الصور من النظام لمكتبة الوسائط (الصورة اللي انزلت قبل ما بتنزل مرة تانية)
    private static function import_images(int $postId, array $sources, array &$warnings): array
    {
        if (!$sources) return [];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $urls = [];
        foreach (array_slice($sources, 0, 40) as $src) {
            $src = esc_url_raw((string) $src);
            if (!$src) continue;

            $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
                'meta_key' => '_makana_src', 'meta_value' => $src]);
            if ($existing) { $urls[] = wp_get_attachment_url($existing[0]); continue; }

            $tmp = download_url($src, 30);
            if (is_wp_error($tmp)) { $warnings[] = 'ما قدرت أنزّل صورة: ' . $tmp->get_error_message(); continue; }

            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
            $ext  = ['image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? 'jpg';

            $attId = media_handle_sideload(['name' => 'makana-' . wp_generate_password(8, false) . '.' . $ext, 'tmp_name' => $tmp], $postId);
            if (is_wp_error($attId)) { @unlink($tmp); $warnings[] = 'صورة مرفوضة: ' . $attId->get_error_message(); continue; }

            update_post_meta($attId, '_makana_src', $src);
            $urls[] = wp_get_attachment_url($attId);
        }
        return array_values(array_filter($urls));
    }

    // إيقاف النشر: الإعلان بيختفي من الموقع بس ما بينحذف
    private static function hide(int $id)
    {
        if (get_post_type($id) !== self::POST_TYPE) {
            return new WP_Error('makana_not_found', 'الإعلان مش موجود', ['status' => 404]);
        }
        $status = get_post_status_object('expired') ? 'expired' : 'draft';
        wp_update_post(['ID' => $id, 'post_status' => $status]);
        return ['id' => $id, 'status' => $status];
    }

    // ── صفحة الإعدادات ────────────────────────────────────────
    public static function menu(): void
    {
        add_options_page('Makana Connector', 'Makana Connector', 'manage_options', 'makana-connector', [__CLASS__, 'page']);
    }

    public static function page(): void
    {
        $o = self::opts();
        $types = self::listing_types();
        ?>
        <div class="wrap" dir="rtl">
            <h1>Makana Connector <small style="font-size:13px;color:#777">v<?php echo esc_html(self::VERSION); ?></small></h1>
            <?php if (!post_type_exists(self::POST_TYPE)): ?>
                <div class="notice notice-error"><p>قالب MyListing مش مفعّل — الإضافة ما رح تقدر تنشر.</p></div>
            <?php endif; ?>
            <p>النظام بيبعت العقارات لهون عن طريق <code>/wp-json/makana/v1</code> باسم مستخدم + Application Password (من Users ← Profile).</p>

            <form method="post" action="options.php">
                <?php settings_fields('makana_connector'); ?>
                <table class="form-table">
                    <tr><th>ربط أنواع العقارات بالأقسام</th><td>
                        <textarea name="<?php echo self::OPTION; ?>[type_map]" rows="6" cols="40" dir="rtl"><?php echo esc_textarea($o['type_map']); ?></textarea>
                        <p class="description">سطر لكل نوع: <code>نوع العقار بالنظام=slug القسم</code></p>
                    </td></tr>
                    <tr><th>القسم الافتراضي</th><td>
                        <select name="<?php echo self::OPTION; ?>[default_type]">
                            <option value="">— بدون (النوع غير المربوط بيرجع خطأ) —</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo esc_attr($t['slug']); ?>" <?php selected($o['default_type'], $t['slug']); ?>><?php echo esc_html($t['title'] . ' (' . $t['slug'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td></tr>
                    <tr><th>ربط حقول يدوي (اختياري)</th><td>
                        <textarea name="<?php echo self::OPTION; ?>[field_map]" rows="4" cols="40" dir="ltr" placeholder="price=job_price"><?php echo esc_textarea($o['field_map']); ?></textarea>
                        <p class="description">بيغلب الربط التلقائي. المفاتيح: <?php echo esc_html(implode(', ', array_keys(self::FIELD_HINTS))); ?></p>
                    </td></tr>
                    <tr><th>حالة الإعلان الجديد</th><td>
                        <select name="<?php echo self::OPTION; ?>[post_status]">
                            <?php foreach (['publish' => 'منشور مباشرة', 'pending' => 'بانتظار المراجعة', 'draft' => 'مسودة'] as $k => $l): ?>
                                <option value="<?php echo $k; ?>" <?php selected($o['post_status'], $k); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td></tr>
                </table>
                <?php submit_button('حفظ'); ?>
            </form>

            <h2>الأقسام والحقول اللي انربطت تلقائياً</h2>
            <?php foreach ($types as $t): $m = self::auto_field_map($t['fields']); ?>
                <h3><?php echo esc_html($t['title']); ?> <code><?php echo esc_html($t['slug']); ?></code></h3>
                <table class="widefat striped" style="max-width:800px"><thead><tr><th>الحقل بالقالب</th><th>slug</th><th>النوع</th><th>بيتعبى من النظام</th></tr></thead><tbody>
                <?php foreach ($t['fields'] as $f): $from = array_search($f['slug'], $m, true); ?>
                    <tr><td><?php echo esc_html($f['label']); ?></td><td><code><?php echo esc_html($f['slug']); ?></code></td><td><?php echo esc_html($f['type']); ?></td>
                        <td><?php echo $from ? '<b>' . esc_html($from) . '</b>' : (in_array($f['slug'], self::RESERVED, true) ? 'أساسي' : '—'); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

Makana_Connector::boot();
