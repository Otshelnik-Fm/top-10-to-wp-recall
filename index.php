<?php
/*

╔═╗╔╦╗╔═╗╔╦╗
║ ║ ║ ╠╣ ║║║ https://otshelnik-fm.ru
╚═╝ ╩ ╚  ╩ ╩

*/


if ( !defined( 'ABSPATH' ) ) exit;


// проверка что не активирован top 10 или доп публикаций
function ttwpr_check_activate(){
    if (!function_exists('tptn_pop_posts') || !function_exists('rcl_get_postslist')){
        return false;
    }
    return true;
}


// добавляем в постлист - шаблон posts-list.php
function ttwpr_add_in_to_postlist($content){

    if(ttwpr_check_activate() === false) return $content; // не активен top 10 или доп публикаций

    global $id;

    $cnt = get_tptn_post_count_only($id, $count = 'total');
    if($cnt){
        $content .= '<span class="tt_wpr_total" title="Всего просмотров">';
            $content .= '<i class="rcli fa-line-chart"></i>';
            $content .= '<span>'.$cnt.'</span>';
        $content .= '</span>';
    }
    return $content;
}
add_filter('content_postslist', 'ttwpr_add_in_to_postlist');



// добавим в вкладку "Публикации" дочернюю "Просмотры за сегодня"
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
add_action('rcl_setup_tabs', 'ttwpr_add_first_sub_tab', 10); // 10 (очередь) - выведется раньше чем вторая с 11


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
add_action('rcl_setup_tabs', 'ttwpr_add_second_sub_tab', 11);


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
            $out = '<i class="rcli fa-pencil ttwpr_type" title="Публикация"></i>';
            break;
        case 'post-group':
            $out = '<i class="rcli fa-users ttwpr_type" title="Публикация в группе"></i>';
            break;
        case 'products':
            $out = '<i class="rcli fa-shopping-cart ttwpr_type" title="Публикация в магазине"></i>';
            break;
    }
    return $out;
}


function ttwpr_notice_box($text, $type = 'success'){
    return '<div class="notify-lk"><div class="'.$type.'">'.$text.'</div></div>';
}


// вывод контента
function ttwpr_html_output($results, $ttl){
    if(!$results) return ttwpr_notice_box('Пока просмотров нет');

    $Table = new Rcl_Table(array(
        'cols' => array(
            array(
                'align' => 'center',
                'title' => 'Тип',
                'width' => 5
            ),
            array(
                'align' => 'center',
                'title' => 'Дата',
                'width' => 15
            ),
            array(
                'title' => 'Заголовок',
                'width' => 65
            ),
            array(
                'align' => 'center',
                'title' => 'Просмотры',
                'width' => 15
            )
        ),
        'table_id' => 'ttwpr_table',
        'zebra' => true,
        'class' => 'ttwpr_views_table',
        'border' => array('table', 'cols', 'rows')
    ));

    foreach($results as $result){
        $name = ttwpr_post_type_convert($result['post_type']);
        $content = '<a target="_blank" href="'.get_permalink($result['ID']).'">'.$result['post_title'].'</a>';
        $status = '<span class="ttwpr_views tt_wpr_total" title="'.$ttl.'">';
            $status .= '<i class="rcli fa-line-chart"></i>';
            $status .= '<span>'.$result['cntaccess'].'</span>';
        $status .= '</span>';

        $Table->add_row(array(
            $name,
            mysql2date('Y-m-d', $result['post_date']),
            $content,
            $status
        ));
    }

    return $Table->get_table();
}



// инлайн стили. Функция сама очистит от пробелов, переносов и сожмёт в строку
function ttwpr_inline_styles($styles){

    if(!rcl_is_office()) return $styles; // отработает только в ЛК

    if(ttwpr_check_activate() === false) return $styles; // не активен top 10 или доп публикаций - не выводим стили

    $styles .= '
        #ttwpr_table a:hover {
            text-decoration: underline;
        }
        .tt_wpr_total {
            align-items: center;
            display: flex;
            white-space: nowrap;
        }
        .tt_wpr_total .rcli {
            color: #2eae5c;
            font-size: .7em;
            margin: 0 8px 0 0;
        }
        .ttwpr_type {
            align-self: center;
            color: #999;
        }
        .rcl_author_postlist .rcl-table__row:not(.rcl-table__row-header) > div:nth-child(2) a {
            flex-grow: 1;
        }
        .rcl_author_postlist .rcl-table__row:not(.rcl-table__row-header) > div:nth-child(2) .rating-rcl {
            order: 1;
        }
    ';

    return $styles;
}
add_filter('rcl_inline_styles', 'ttwpr_inline_styles', 10);
