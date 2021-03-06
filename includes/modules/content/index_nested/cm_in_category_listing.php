<?php
/*
  Copyright (c) 2018, G Burton
  All rights reserved.

  Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

  1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

  3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

  class cm_in_category_listing {
    var $code;
    var $group;
    var $title;
    var $description;
    var $sort_order;
    var $enabled = false;

    function __construct() {
      $this->code = get_class($this);
      $this->group = basename(dirname(__FILE__));

      $this->title = MODULE_CONTENT_IN_CATEGORY_LISTING_TITLE;
      $this->description = MODULE_CONTENT_IN_CATEGORY_LISTING_DESCRIPTION;
      $this->description .= '<div class="secWarning">' . MODULE_CONTENT_BOOTSTRAP_ROW_DESCRIPTION . '</div>';

      if ( defined('MODULE_CONTENT_IN_CATEGORY_LISTING_STATUS') ) {
        $this->sort_order = MODULE_CONTENT_IN_CATEGORY_LISTING_SORT_ORDER;
        $this->enabled = (MODULE_CONTENT_IN_CATEGORY_LISTING_STATUS == 'True');
      }      
    }

    function execute() {
      global $oscTemplate, $current_category_id, $OSCOM_category;
      
      $content_width  = MODULE_CONTENT_IN_CATEGORY_LISTING_CONTENT_WIDTH;
      $category_card_layout = MODULE_CONTENT_IN_CATEGORY_LISTING_LAYOUT;
      
      $category_name  = $OSCOM_category->getData($current_category_id, 'name');
      $category_level = $OSCOM_category->setMaximumLevel(1);
      $category_array = $OSCOM_category->buildBranchArray($current_category_id, $category_level);

      ob_start();
      include('includes/modules/content/' . $this->group . '/templates/tpl_' . basename(__FILE__));
      $template = ob_get_clean();

      $oscTemplate->addContent($template, $this->group);
    }

    function isEnabled() {
      return $this->enabled;
    }

    function check() {
      return defined('MODULE_CONTENT_IN_CATEGORY_LISTING_STATUS');
    }

    function install() {
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Sub-Category Listing Module', 'MODULE_CONTENT_IN_CATEGORY_LISTING_STATUS', 'True', 'Should this module be enabled?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Content Width', 'MODULE_CONTENT_IN_CATEGORY_LISTING_CONTENT_WIDTH', '12', 'What width container should the content be shown in?', '6', '2', 'tep_cfg_select_option(array(\'12\', \'11\', \'10\', \'9\', \'8\', \'7\', \'6\', \'5\', \'4\', \'3\', \'2\', \'1\'), ', now())");
      tep_db_query("INSERT INTO configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Category Card Layout', 'MODULE_CONTENT_IN_CATEGORY_LISTING_LAYOUT', 'card-deck', 'What Layout suits your shop?  See https://getbootstrap.com/docs/4.4/components/card/#card-layout <div class=\"secWarning\">card-columns is a special use case that will not suit most shops as card-columns is very difficult to layout and sort by...</div>', '6', '3', 'tep_cfg_select_option(array(\'card-group\', \'card-deck\', \'card-columns\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Items In Each Row (SM)', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_SM', '2', 'How many products should display per Row in SM (Small) viewport?', '6', '4', 'tep_cfg_select_option(array(\'12\', \'11\', \'10\', \'9\', \'8\', \'7\', \'6\', \'5\', \'4\', \'3\', \'2\', \'1\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Items In Each Row (MD)', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_MD', '3', 'How many products should display per Row in MD (Medium) viewport?', '6', '4', 'tep_cfg_select_option(array(\'12\', \'11\', \'10\', \'9\', \'8\', \'7\', \'6\', \'5\', \'4\', \'3\', \'2\', \'1\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Items In Each Row (LG)', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_LG', '4', 'How many products should display per Row in LG (Large) viewport?', '6', '4', 'tep_cfg_select_option(array(\'12\', \'11\', \'10\', \'9\', \'8\', \'7\', \'6\', \'5\', \'4\', \'3\', \'2\', \'1\'), ', now())");
      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Items In Each Row (XL)', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_XL', '6', 'How many products should display per Row in XL (Extra Large) viewport?', '6', '4', 'tep_cfg_select_option(array(\'12\', \'11\', \'10\', \'9\', \'8\', \'7\', \'6\', \'5\', \'4\', \'3\', \'2\', \'1\'), ', now())");

      tep_db_query("insert into configuration (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_CONTENT_IN_CATEGORY_LISTING_SORT_ORDER', '200', 'Sort order of display. Lowest is displayed first.', '6', '4', now())");
    }

    function remove() {
      tep_db_query("delete from configuration where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    
    function keys() {
      return array('MODULE_CONTENT_IN_CATEGORY_LISTING_STATUS', 'MODULE_CONTENT_IN_CATEGORY_LISTING_CONTENT_WIDTH', 'MODULE_CONTENT_IN_CATEGORY_LISTING_LAYOUT',  'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_SM', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_MD', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_LG', 'MODULE_CONTENT_IN_CATEGORY_LISTING_DISPLAY_ROW_XL', 'MODULE_CONTENT_IN_CATEGORY_LISTING_SORT_ORDER');
    }  
  }
  