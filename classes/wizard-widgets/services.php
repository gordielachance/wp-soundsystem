<?php
class WP_Soundsystem_Wizard_Services_Widget extends WP_Soundsystem_Wizard_Widget{

    function __construct(){
        $this->slug = 'services';
        $this->name = __('Supported services','wpsstm');
    }

    function get_output(){
        
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
        );

        //format
        //separate services & subservices
        foreach ($services as $key=>$item){
            $item = $services[$key] = wp_parse_args($item,$default);
            
            if ($item['parent_slug']){
                $slug = $item['parent_slug'];
                $subservices[$slug][] = $item;
                unset($services[$key]);
            }
        }

        //children
        foreach ($services as $key=>$service){

            $service_slug = $service['slug'];
            $page_els = array();
            $page_str = null;
            
            $pages = ( isset($subservices[$service_slug]) ) ? $subservices[$service_slug] : array();
            
            if ( $pages ){
                foreach ($pages as $page){
                    $page_els[] = sprintf('<a class="wizard-service wizard-service-%s" data-wpsstm-wizard-hover="%s" href="%s" title="%s" target="_blank">%s</a>',$page['slug'],$page['example'],$page['url'],$page['desc'],$page['name']);
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
            
            $example = $service['example'] ? $service['example'] : sprintf( '%s...',trailingslashit($service['url']) );
            
            $service_label = $service['name'] . $page_str;
            $service_els[] = sprintf('<a class="wizard-service wizard-service-%s" data-wpsstm-wizard-hover="%s" href="%s" title="%s" target="_blank">%s</a>',$service['slug'],$example,$service['url'],$service['desc'],$service_label);
        }
        
        if ( empty($service_els) ) return;
        
        //wrap
        $service_els = array_map(
           function ($el) {
              return "<li>{$el}</li>";
           },
           $service_els
        );
        
        return sprintf('<ul id="wpsstm-wizard-services-list">%s</ul>',implode("\n",$service_els));
    }
}

function register_tracklist_services_widget($helpers){
    $helpers[] = 'WP_Soundsystem_Wizard_Services_Widget';
    return $helpers;
}

add_filter('wpsstm_get_wizard_widgets','register_tracklist_services_widget');