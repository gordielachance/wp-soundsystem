<?php
$services = array();
$services = apply_filters('wpsstm_wizard_services_links',array());
$service_els = array();

$subservices = array();

$default = array(
    'slug'          => null,
    'parent_slug'   => null,
    'name'          => null,
    'desc'          => null,
    'url'           => '#',
    'example'       => null,
    'pages'         => array(), //sublinks
);

foreach ($services as $key=>$service){
    $example = null;
    $service = $services[$key] = wp_parse_args($service,$default);
    $service_slug = $service['slug'];
    $page_els = array();
    $page_str = null;

    if ( $service['pages'] ){
        foreach ($service['pages'] as $page){
            $page = wp_parse_args($page,$default);

            $page_attr = array(
                'href' =>   $page['url'],
                'data-wpsstm-wizard-hover' => $page['example'],
                'target' => '_blank',
            );

            $page_attr_str = wpsstm_get_html_attr($page_attr);

            $page_els[] = sprintf('<a %s>%s</a>',$page_attr_str,$page['name']);
        }

        //wrap
        $page_els = array_map(
           function ($el) {
              return "<li>{$el}</li>";
           },
           $page_els
        );

        $page_str = sprintf('<ul class="wpsstm-wizard-child-service-list">%s</ul>',implode("\n",$page_els));
    }

    $service_attr = array(
        'class' =>  implode( ' ',array('wizard-service','wizard-service-' . $service['slug']) ),
        'title' =>  $service['desc'],
    );

    if ($service['url'] !== false){
        $service_attr['href'] = $service['url'];
        $service_attr['target'] = '_blank';
        $service_attr['title'] = $service['desc'];
    }

    $service_attr_str = wpsstm_get_html_attr($service_attr);

    if ($service['url'] !== false){
        $service_els[] = sprintf('<a %s>%s</a>%s',$service_attr_str,$service['name'],$page_str);
    }else{
        $service_els[] = sprintf('<span %s>%s</span>%s',$service_attr_str,$service['name'],$page_str);
    }

}

if ( empty($service_els) ) return;

//wrap
$service_els = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $service_els
);
?>
<section id="wpsstm-wizard-services">
    <h3><?php _e('Supported services','wpsstm');?></h3>
    <ul>
        <?php echo implode("\n",$service_els); ?>
    </ul>
</section>