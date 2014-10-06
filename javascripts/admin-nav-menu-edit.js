(function($) {

    gk_toggle_autopopulate_options = function (id, checked) {
        if( checked ) $('.edit-menu-item-autopopulate-options-'+id).show();
        else $('.edit-menu-item-autopopulate-options-'+id).hide();
    }
    
    gk_toggle_autopopulate_posttype_options = function (id) {
    
        var value = $('#edit-menu-item-autopopulate_type-'+id+'-posttype:checked').val();
        if(value=='posttype') {
           $('.edit-menu-item-autopopulate-posttype-options-'+id).show();
           gk_autopopulate_posttype_taxonomies (id, $('#edit-menu-item-autopopulate_posttype-'+id));
        }
    
        
    }
    
    gk_autopopulate_posttype_taxonomies = function (id, elem) {
        elem = $(elem);
        $('#edit-menu-item-autopopulate_taxonomy-'+id).load( ajaxurl, 
                                                             {'action':'gsm_taxonomies_select', 'post_type': elem.val()}, 
                                                             $.proxy( gk_autopopulate_posttype_taxonomies_c, id));  
       
    }
    gk_autopopulate_posttype_taxonomies_c = function (responseText, textStatus, xhr) {
        
        if( responseText == '' ) {
           
            $('#edit-menu-item-autopopulate_posttype_type-'+this+'-taxonomies').attr("disabled", "disabled");     
         
            $('input:radio[id=edit-menu-item-autopopulate_posttype_type-'+this+'-posts]').attr("checked", "checked");
           
            
        }
        else $('#edit-menu-item-autopopulate_posttype_type-'+this+'-taxonomies').removeAttr("disabled");
       
       gk_toggle_autopopulate_posttax_options(this, 'input:radio[name=menu-item-autopopulate_posttype_type['+this+']]');
       
    }
    
    gk_toggle_autopopulate_posttax_options = function (id, elem) {
        
        elem = $(elem);
        if(elem.val() == 'posts') {
            $('.gsm_taxonomies_options-'+id).hide();
            $('.gsm_posts_options-'+id).show();
        }
        else if(elem.val() == 'taxonomies') {
            $('.gsm_taxonomies_options-'+id).show();
            $('.gsm_posts_options-'+id).hide();
        } 
       
    }

})(jQuery);

