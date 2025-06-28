# Entwickler-Dokumentation: Hooks, Actions & Filter

Diese Datei listet alle wichtigen Actions, Filter und Hooks des PS Multisite Index Plugins auf. Sie dient als Referenz für Entwickler, die das Plugin erweitern oder eigene Integrationen bauen möchten.

## Übersicht

- [Actions](#actions)
- [Filter](#filter)
- [Beispiele](#beispiele)

---

## Actions

### postindexer_index_post
Wird aufgerufen, wenn ein Beitrag indexiert wird.

**Parameter:**
- `$post` (WP_Post)

### postindexer_remove_indexed_post
Wird aufgerufen, wenn ein Beitrag aus dem Index entfernt wird.

**Parameter:**
- `$post_id` (int)
- `$blog_id` (int)

### postindexer_firstpass_cron, postindexer_secondpass_cron, postindexer_tagtidy_cron, postindexer_postmetatidy_cron, postindexer_agedpoststidy_cron
Cronjobs für verschiedene Index-Operationen.

---

## Filter

### postindexer_is_blog_indexable
Erlaubt es, die Indexierbarkeit einer Seite zu beeinflussen.

**Parameter:**
- `$indexing` (bool)
- `$blog_id` (int)

### postindexer_is_post_indexable
Erlaubt es, die Indexierbarkeit eines Beitrags zu beeinflussen.

**Parameter:**
- `$indexing` (bool)
- `$post` (WP_Post)
- `$blog_id` (int)

---

## Netzwerkweite Filter (networkquery.php)

- `network_posts_search`, `network_posts_where`, `network_posts_join`, ...
- `comment_feed_join`, `comment_feed_where`, ...
- Siehe Quelltext für vollständige Liste und Parameter.

---

## Beispiel: Eigenen Filter nutzen

```php
add_filter('postindexer_is_post_indexable', function($indexing, $post, $blog_id) {
    // Nur Beiträge mit bestimmtem Meta-Feld indexieren
    if (get_post_meta($post->ID, 'mein_meta', true) === 'noindex') {
        return false;
    }
    return $indexing;
}, 10, 3);
```

---

Weitere Hooks und Details findest du direkt im Quelltext (z.B. in `classes/class.model.php`, `classes/networkquery.php`).

> Stand: 27.06.2025
