<?php
/*
    Интеграция WP-Recall с плагином "Top 10 - Popular posts plugin for WordPress"
    https://wordpress.org/plugins/top-10/
*/


// проверка что не активирован top 10 или доп публикаций
function ttwpr_check_activate(){
    if (!function_exists('tptn_pop_posts') || !function_exists('rcl_add_postlist_posts')){
        return false;
    }
}


// добавляем в постлист - шаблон posts-list.php
add_filter('content_postslist','ttwpr_add_in_to_postlist');
function ttwpr_add_in_to_postlist($content){

    if(ttwpr_check_activate() === false) return $content; // не активен top 10 или доп публикаций

    global $id;

    $cnt = get_tptn_post_count_only($id, $count = 'total');
    if($cnt){
        $content .= '<span class="tt_wpr_total" title="Всего просмотров">';
            $content .= '<i class="fa fa-line-chart"></i>'.$cnt;
        $content .= '</span>';
    }
    return $content;
}



// инлайн стили. Функция сама очистит от пробелов, переносов и сожмёт в строку
add_filter('rcl_inline_styles','ttwpr_inline_styles',10);
function ttwpr_inline_styles($styles){

    if(!rcl_is_office()) return $styles; // отработает только в ЛК

    if(ttwpr_check_activate() === false) return $styles; // не активен top 10 или доп публикаций - не выводим стили

    $styles .= '
        .tt_wpr_total {
            background-color: rgba(219, 219, 219, 0.6);
            float: right;
            font: 12px/1 Helvetica,serif,arial;
            margin: 0 4px;
            padding: 5px;
            white-space: nowrap;
        }
        .tt_wpr_total .fa {
            margin: 0 5px 0 0;
        }
        #subtab-ttwpr_all {
            box-shadow: 0 0 1px 1px #ddd;
        }
        .ttwpr_line{
            color: #777;
            display: inline-block;
            padding: 5px 0;
            width: 100%;
        }
        .ttwpr_line:nth-child(2n){
            background-color: rgba(237, 237, 237, 0.8);
        }
        .ttwpr_type {
            display: inline-block;
            padding: 0 0 0 5px;
            text-align: center;
            width: 28px;
        }
        .ttwpr_date {
            font-size: 12px;padding: 0 10px 0 5px;
        }
    ';
    return $styles;
}



// добавим в вкладку "Публикации" дочернюю "Просмотры за сегодня"
add_action('rcl_setup_tabs','ttwpr_add_first_sub_tab',10); // 10 (очередь) - выведется раньше чем вторая с 11
function ttwpr_add_first_sub_tab(){

    if(ttwpr_check_activate() === false) return false; // не активен top 10 или доп публикаций - не выводим вкладку

    $subtab = array(
        'id'=> 'ttwpr_today',
        'name'=> 'Просмотры за сегодня',
        'icon' => 'fa-clock-o',
        'callback'=>array(
            'name'=>'ttwpr_views_daily',
        )
    );
    rcl_add_sub_tab('publics',$subtab);
}


// обработчик вкладки "Просмотры за сегодня"
function ttwpr_views_daily($user_lk){
    global $wpdb;

    $t_day = get_date_from_gmt(date('Y-m-d H:i:s'),'Y-m-d'); // сегодня - настройки локали вп (вида 2016-12-21)

    $sql = "SELECT DISTINCT t1.ID, t1.post_date, t1.post_title, t1.post_type, "
            . "SUM(t2.cntaccess) as cntaccess "
            . "FROM ".$wpdb->prefix."top_ten_daily  AS t2 "
            . "INNER JOIN $wpdb->posts AS t1 "
            . "ON t2.postnumber=t1.ID "
            . "WHERE "
                . "t1.post_author = ".$user_lk." "
                ."AND t1.post_status = 'publish' "
                ."AND t2.dp_date LIKE '%".$t_day."%' "
                ."AND t1.post_type IN ('post', 'post-group', 'products') "
                . "GROUP BY  postnumber "
            . "ORDER BY "
                . "cntaccess DESC "
            . "LIMIT 0, 50";

	$results = $wpdb->get_results($sql, ARRAY_A);

    return ttwpr_html_output($results, $ttl = 'Сегодня просмотров');
}



// добавим в вкладку "Публикации" дочернюю "Просмотры за всё время"
add_action('rcl_setup_tabs','ttwpr_add_second_sub_tab',11);
function ttwpr_add_second_sub_tab(){

    if(ttwpr_check_activate() === false) return false; // не активен top 10 или доп публикаций - не выводим вкладку

    $subtab = array(
        'id'=> 'ttwpr_all',
        'name'=> 'Просмотры за всё время',
        'icon' => 'fa-line-chart',
        'callback'=>array(
            'name'=>'ttwpr_views_all',
        )
    );
    rcl_add_sub_tab('publics',$subtab);
}


// обработчик вкладки "Просмотры за всё время"
function ttwpr_views_all($user_lk){
    global $wpdb;

    $sql = "SELECT t1.ID, t1.post_date, t1.post_title, t1.post_type, t2.cntaccess "
            . "FROM $wpdb->posts AS t1 "
            . "INNER JOIN ".$wpdb->prefix."top_ten AS t2 "
            . "ON t1.ID = t2.postnumber "
            . "WHERE "
                . "t1.post_author = ".$user_lk." "
                ."AND t2.cntaccess IS NOT NULL "
                ."AND t1.post_status = 'publish' "
                ."AND t1.post_type  IN ('post', 'post-group', 'products') "
            . "ORDER BY "
                . "t2.cntaccess DESC "
            . "LIMIT 0, 50";

	$results = $wpdb->get_results($sql, ARRAY_A);

    return ttwpr_html_output($results, $ttl = 'Всего просмотров');
}



// конверт типа записи в иконку
function ttwpr_post_type_convert($type){
    switch($type){
        case 'post':
            $out = '<i class="fa fa-pencil" title="Публикация"></i>';
            break;
        case 'post-group':
            $out = '<i class="fa fa-users" title="Публикация в группе"></i>';
            break;
        case 'products':
            $out = '<i class="fa fa-shopping-cart" title="Публикация в магазине"></i>';
            break;
    }
    return $out;
}



// вывод контента
function ttwpr_html_output($results, $ttl){
    foreach($results as $result){
        $name = ttwpr_post_type_convert($result['post_type']);
        $out .= '<div class="ttwpr_line ttwpr_all">';
            $out .= '<span class="ttwpr_type">'.$name.'</span>';
            $out .= '<span class="ttwpr_date">'.mysql2date('Y-m-d', $result['post_date']).'</span>';
            $out .= '<span class="ttwpr_title">';
                $out .= '<a target="_blank" href="'.get_permalink($result['ID']).'">'.$result['post_title'].'</a>';
            $out .= '</span>';
            $out .= '<span class="ttwpr_views tt_wpr_total" title="'.$ttl.'">';
                $out .= '<i class="fa fa-line-chart"></i>'.$result['cntaccess'];
            $out .= '</span>';
        $out .= '</div>';
    }
    return $out;
}





// прямые sql запросы для дебага
/*
// популярные за все время с автором
SELECT t1.ID, t1.post_date, t1.post_title, t1.post_type, t2.cntaccess
FROM `wp_posts` AS t1
INNER JOIN `wp_top_ten` AS t2
ON t1.ID = t2.postnumber
WHERE t1.post_author = 1
AND t1.post_status = 'publish'
AND t2.cntaccess IS NOT NULL
AND t1.post_type  IN ('post', 'post-group', 'products')

ORDER BY t2.cntaccess DESC

LIMIT 0, 10
*/

/*
// популярные за день с автором
SELECT DISTINCT t1.ID, t1.post_title, t1.post_type,
SUM(t2.cntaccess) as sum_count
FROM wp_top_ten_daily  AS t2
INNER JOIN wp_posts AS t1
ON t2.postnumber=t1.ID
WHERE t1.post_author = 1
AND t1.post_status = 'publish'
AND t2.dp_date LIKE '%2016-12-21%'
AND t1.post_type IN ('post', 'post-group', 'products')
GROUP BY  postnumber
ORDER BY  sum_count DESC
LIMIT 0, 10
 */