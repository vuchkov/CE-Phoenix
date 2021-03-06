<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2020 osCommerce

  Released under the GNU General Public License
*/

  require 'includes/application_top.php';

  $action = ($_GET['action'] ?? '');

  $OSCOM_Hooks->call('customers', 'preAction');

  if (tep_not_null($action)) {
    switch ($action) {
      case 'update':
        $_SESSION['customer_id'] = (int)tep_db_prepare_input($_GET['cID']);
        $customer_details = $customer_data->process();
        unset($_SESSION['customer_id']);

        if (!empty($customer_details)) {
          $customer_details['id'] = (int)tep_db_prepare_input($_GET['cID']);
          if (empty($customer_details['password'])) {
            unset($customer_details['password']);
          } else {
            require 'includes/functions/password_funcs.php';
          }
          $customer_data->update($customer_details, ['id' => $customer_details['id']]);
          tep_db_query("UPDATE customers_info SET customers_info_date_account_last_modified = NOW() WHERE customers_info_id = " . (int)$customer_details['id']);

          $OSCOM_Hooks->call('customers', 'afterUpdate');

          tep_redirect(tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $customer_details['id']));
        }

        // if we reach here, we did not redirect, so there was some kind of error
        $action = 'edit';
        break;
      case 'deleteconfirm':
        $customers_id = tep_db_prepare_input($_GET['cID']);

        if (isset($_POST['delete_reviews']) && ($_POST['delete_reviews'] == 'on')) {
          tep_db_query("DELETE r, rd FROM reviews r LEFT JOIN reviews_description rd ON r.reviews_id = rd.reviews_id WHERE r.customers_id = " . (int)$customers_id);
        } else {
          tep_db_query("UPDATE reviews SET customers_id = NULL WHERE customers_id = " . (int)$customers_id);
        }

        tep_db_query("DELETE FROM address_book WHERE customers_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM customers WHERE customers_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM customer_data WHERE customers_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM customers_info WHERE customers_info_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM customers_basket WHERE customers_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM customers_basket_attributes WHERE customers_id = " . (int)$customers_id);
        tep_db_query("DELETE FROM whos_online WHERE customer_id = " . (int)$customers_id);

        $OSCOM_Hooks->call('customers', 'afterDelete');

        tep_redirect(tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action'])));
        break;
      default:
        $customer_details_query = tep_db_query($customer_data->build_read($customer_data->list_all_capabilities(), 'both', [ 'id' => (int)$_GET['cID'] ]));
        $customer_details = tep_db_fetch_array($customer_details_query);
    }
  }

  $OSCOM_Hooks->call('customers', 'postAction');

  require 'includes/template_top.php';
  ?>
  
  <div class="row">
    <div class="col">
      <h1 class="display-4 mb-2"><?php echo HEADING_TITLE; ?></h1>
    </div>
    <div class="col text-right align-self-center">
      <?php
      if (!isset($_GET['action'])) {
        echo tep_draw_form('search', 'customers.php', '', 'get');
          echo '<div class="input-group">';
            echo '<div class="input-group-prepend">';
              echo '<span class="input-group-text">' . HEADING_TITLE_SEARCH . '</span>';
            echo '</div>';
            echo tep_draw_input_field('search');
          echo '</div>';
          echo tep_hide_session_id();
        echo '</form>';
      }
      else {
        echo tep_draw_bootstrap_button(IMAGE_CANCEL, 'fas fa-angle-left', tep_href_link('customers.php', tep_get_all_get_params(array('action'))), null, null, 'btn-light');
      }
      ?>
    </div>
  </div>
  
  <?php
  if ($action == 'edit' || $action == 'update') {
    ?>
   
  <div class="contentContainer">
    <?php
    $oscTemplate = new oscTemplate();
    echo tep_draw_form('customers', 'customers.php', tep_get_all_get_params(['action']) . 'action=update', 'post');
    echo tep_draw_hidden_field('default_address_id', $customer_data->get('address_id', $customer_details));

    $cwd = getcwd();
    chdir(DIR_FS_CATALOG);
    
    $grouped_modules = $customer_data->get_grouped_modules();
    $customer_data_group_query = tep_db_query(<<<'EOSQL'
SELECT customer_data_groups_id, customer_data_groups_name
 FROM customer_data_groups
 WHERE language_id =
EOSQL
      . (int)$languages_id . ' ORDER BY cdg_vertical_sort_order, cdg_horizontal_sort_order');

    while ($customer_data_group = tep_db_fetch_array($customer_data_group_query)) {
      if (empty($grouped_modules[$customer_data_group['customer_data_groups_id']])) {
        continue;
      }
      ?>
      
     <h5><?php echo $customer_data_group['customer_data_groups_name']; ?></h5>

      <?php
      foreach ((array)$grouped_modules[$customer_data_group['customer_data_groups_id']] as $module) {
        $module->display_input($customer_details);
      }
    }
    
    chdir($cwd);
    
    echo tep_draw_bootstrap_button(IMAGE_SAVE, 'fas fa-save', null, 'primary', null, 'btn-success btn-block btn-lg');
    ?>

  </form>
</div>
<?php
  } else {
?>

  <div class="row no-gutters">
    <div class="col">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead class="thead-dark">
            <tr>
              <th><?php echo TABLE_HEADING_NAME; ?></th>
              <th class="text-right"><?php echo TABLE_HEADING_ACCOUNT_CREATED; ?></th>
              <th class="text-right"><?php echo TABLE_HEADING_ACTION; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $customers_query_raw = $customer_data->build_read([ 'id', 'sortable_name', 'email_address', 'country_id' ], 'customers');
            $keywords = null;
            if (tep_not_null($_GET['search'] ?? '')) {
              $keywords = tep_db_prepare_input($_GET['search']);
              $customer_data->add_search_criteria($customers_query_raw, $keywords);
            }
            $customers_query_raw = $customer_data->add_order_by($customers_query_raw, ['sortable_name']);

            $customers_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows);
            $customers_query = tep_db_query($customers_query_raw);

            while ($customers = tep_db_fetch_array($customers_query)) {
              $info_query = tep_db_query(<<<'EOSQL'
SELECT customers_info_date_account_created AS date_account_created,
       customers_info_date_account_last_modified AS date_account_last_modified,
       customers_info_date_of_last_logon AS date_last_logon,
       customers_info_number_of_logons AS number_of_logons
 FROM customers_info
 WHERE customers_info_id =
EOSQL
        . (int)$customer_data->get('id', $customers));
            $info = tep_db_fetch_array($info_query);

            if (!isset($cInfo) && (!isset($_GET['cID']) || ($_GET['cID'] === $customer_data->get('id', $customers)))) {
              $reviews_query = tep_db_query("SELECT COUNT(*) AS number_of_reviews FROM reviews WHERE customers_id = " . (int)$customer_data->get('id', $customers));
              $reviews = tep_db_fetch_array($reviews_query);

              $cInfo_array = array_merge($customers, (array)$info, $reviews);

              // preload necessary fields not already used
              $customer_data->get([ 'sortable_name', 'name', 'email_address', 'country_id', 'id' ], $cInfo_array);
              $cInfo = new objectInfo($cInfo_array);

              $href = tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $cInfo->customers_id . '&action=edit');
              $icon = '<i class="fas fa-chevron-circle-right text-info"></i>';
            } else {
              $href = tep_href_link('customers.php', tep_get_all_get_params(['cID']) . 'cID=' . $customer_data->get('id', $customers));
              $icon = '<a href="' . $href . '"><i class="fas fa-info-circle text-muted"></i></a>';
            }
            ?>
              <tr onclick="document.location.href='<?php echo $href; ?>'">
                <td class="dataTableContent"><?php echo $customer_data->get('sortable_name', $customers); ?></td>
                <td class="dataTableContent" align="right"><?php echo tep_date_short($info['date_account_created']); ?></td>
                <td class="dataTableContent" align="right"><?php echo $icon; ?></td>
              </tr>
              <?php
            }
            ?>
          </tbody>
        </table>
      </div>
      
      <div class="row my-1">
        <div class="col"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_CUSTOMERS); ?></div>
        <div class="col text-right mr-2"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(['page', 'info', 'x', 'y', 'cID'])); ?></div>
      </div>
      
      <?php
      if (isset($keywords)) {
        echo tep_draw_bootstrap_button(IMAGE_RESET, 'fas fa-angle-left', tep_href_link('customers.php'), null, null, 'btn-light');
      }
      ?>

      
    </div>

<?php
    $heading = [];
    $contents = [];

    switch ($action) {
      case 'confirm':
        $heading[] = ['text' => TEXT_INFO_HEADING_DELETE_CUSTOMER];

        $contents = ['form' => tep_draw_form('customers', 'customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $cInfo->id . '&action=deleteconfirm')];
        $contents[] = ['text' => TEXT_DELETE_INTRO . '<br /><br /><strong>' . $cInfo->name . '</strong>'];
        if (isset($cInfo->number_of_reviews) && ($cInfo->number_of_reviews) > 0) $contents[] = ['text' => tep_draw_checkbox_field('delete_reviews', 'on', true) . ' ' . sprintf(TEXT_DELETE_REVIEWS, $cInfo->number_of_reviews)];
        $contents[] = ['class' => 'text-center', 'text' => tep_draw_button(IMAGE_DELETE, 'trash', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $cInfo->id)),
        ];
        break;
      default:
        if (($cInfo ?? null) instanceof objectInfo) {
          $heading[] = ['text' => $cInfo->name];

          $contents[] = ['class' => 'text-center', 'text' => tep_draw_button(IMAGE_EDIT, 'document', tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $cInfo->id . '&action=edit')) . tep_draw_button(IMAGE_DELETE, 'trash', tep_href_link('customers.php', tep_get_all_get_params(['cID', 'action']) . 'cID=' . $cInfo->id . '&action=confirm')) . tep_draw_button(IMAGE_ORDERS, 'cart', tep_href_link('orders.php', 'cID=' . $cInfo->id)) . tep_draw_button(IMAGE_EMAIL, 'mail-closed', tep_href_link('mail.php', 'customer=' . urlencode($cInfo->email_address))),
          ];
          $contents[] = ['text' => sprintf(TEXT_DATE_ACCOUNT_CREATED, tep_date_short($cInfo->date_account_created))];
          $contents[] = ['text' => sprintf(TEXT_DATE_ACCOUNT_LAST_MODIFIED, tep_date_short($cInfo->date_account_last_modified))];
          $contents[] = ['text' => sprintf(TEXT_INFO_DATE_LAST_LOGON, tep_date_short($cInfo->date_last_logon))];
          $contents[] = ['text' => sprintf(TEXT_INFO_NUMBER_OF_LOGONS, $cInfo->number_of_logons)];

          if ($customer_data->has('country_name') && isset($cInfo->country_id)) {
            $country_query = tep_db_query("SELECT * FROM countries WHERE countries_id = " . (int)$cInfo->country_id);
            $country = (array)tep_db_fetch_array($country_query);

            $contents[] = ['text' => sprintf(TEXT_INFO_COUNTRY, $country['countries_name'])];
          }

          $contents[] = ['text' => sprintf(TEXT_INFO_NUMBER_OF_REVIEWS, $cInfo->number_of_reviews)];
        }
        break;
    }

    if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
    echo '<div class="col-12 col-sm-3">';
      $box = new box;
      echo $box->infoBox($heading, $contents);
    echo '</div>';
  }
?>
  
  </div>
  
<?php
  }

  require 'includes/template_bottom.php';
  require 'includes/application_bottom.php';
?>
