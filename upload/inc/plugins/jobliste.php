<?php
// error_reporting(1);
// ini_set('display_errors', true);
// error_reporting();
// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


function jobliste_info()
{
  global $lang;
  return array(
    "name" => "Risuenas Jobliste",
    "description" => "Jobliste mit Überkategorien, Kategorien und Subkategorien. Mehrfacheintragungen möglich. Überkategorien sind tabbar.",
    "website" => "https://github.com/katjalennartz",
    "author" => "risuena",
    "authorsite" => "https://github.com/katjalennartz",
    "version" => "1.0",
    "compatibility" => "18*"
  );
}

function jobliste_is_installed()
{
  global $db;
  if ($db->table_exists("jl_maincat")) {
    return true;
  }
  return false;
}

function jobliste_install()
{
  global $db, $cache;
  //reste löschen wenn was schiefgegangen ist
  jobliste_uninstall();

  jobliste_database();

  jobliste_add_settings();

  jobliste_add_templates();

  $css = jobliste_stylesheet();

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  $sid = $db->insert_query("themestylesheets", $css);
  $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

  $tids = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
  }
}

function jobliste_uninstall()
{
  global $db, $cache;
  //Tabelle löschen wenn existiert
  if ($db->table_exists("jl_maincat")) {
    $db->drop_table("jl_maincat");
  }
  if ($db->table_exists("jl_subcat")) {
    $db->drop_table("jl_subcat");
  }
  if ($db->table_exists("jl_cat")) {
    $db->drop_table("jl_cat");
  }
  if ($db->table_exists("jl_entry")) {
    $db->drop_table("jl_entry");
  }

  //TEMPLATES LÖSCHEN 
  $db->delete_query("templates", "title LIKE 'jobliste%'");
  $db->delete_query("templategroups", "prefix = 'jobliste'");

  //CSS LÖSCHEN
  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
  $db->delete_query("themestylesheets", "name = 'jobliste.css'");
  $query = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($query)) {
    update_theme_stylesheet_list($theme['tid']);
  }
  //EINSTELLUNGEN LÖSCHEN
  $db->delete_query('settings', "name LIKE 'jobliste%'");
  $db->delete_query('settinggroups', "name = 'jobliste'");
  rebuild_settings();
}

function jobliste_activate()
{
}

function jobliste_deactivate()
{
}


/**
 * Verwaltung der Darstellung im Forum (Ausgabe der Liste)
 * und ermöglicht das Reservieren an sich 
 */
$plugins->add_hook("misc_start", "jobliste_main");
function jobliste_main()
{
  global $list_bit, $menu, $page, $mybb, $db, $templates, $header, $footer, $theme, $headerinclude, $jobliste_main, $jobliste_addcat_mods, $lang, $jobliste_addsubcat, $jobliste_bituser, $jobliste_tab_js;
  //Jobseite
  if ($mybb->get_input('action', MyBB::INPUT_STRING) == "jobliste") {
    $thisuser = $mybb->user['uid'];
    $jobliste_typ = "";
    $joblist_modstuff = "";
    $jobliste_tabbit = "";
    $jobliste_add = "";
    $jcid = "";
    //just working for my own forum - ignore it :D 
    // if ($db->table_exists("generallists")) {
    //   $lists_menuentry = generallists_buildmenu();
    //   eval("\$generallists_menu .= \"" . $templates->get("generallists_menu") . "\";");
    //   $list['name'] = "Jobliste";
    //   $list['text'] = "";
    // }

    //Hinzufügen der Breadcrumb
    add_breadcrumb("Jobliste", "misc.php?action=jobliste");

    // Nur Moderatoren dürfen Kategorien hinzufügen
    if (($mybb->usergroup['canmodcp'] == 1)) {
      eval("\$jobliste_addcat_mods .= \"" . $templates->get("jobliste_addcat_mods") . "\";");
    }

    //Mainkategorie/Tyo erstellen
    if (isset($mybb->input['jobcat_send']) && $mybb->usergroup['canmodcp'] == 1) {

      $insert = array(
        "jm_title" => $db->escape_string($mybb->get_input("add_cat_title", MyBB::INPUT_STRING)),
        "jm_subtitle" => $db->escape_string($mybb->get_input("add_cat_subtitle", MyBB::INPUT_STRING)),
        "jm_descr" => $db->escape_string($mybb->get_input("add_cat_descr", MyBB::INPUT_STRING)),
        "jm_sort" => $mybb->get_input("add_cat_sort", MyBB::INPUT_INT)
      );

      $db->insert_query("jl_maincat", $insert);
      redirect("misc.php?action=jobliste");
    }

    //Eine Kategorie erstellen
    if (isset($mybb->input['jobcat_jc_send']) && $mybb->usergroup['canmodcp'] == 1) {
      $insert = array(
        "jc_title" => $db->escape_string($mybb->get_input("add_jccat_title", MyBB::INPUT_STRING)),
        "jc_maincat" => $mybb->get_input("jobcat", MyBB::INPUT_INT),
        "jc_sort" => $mybb->get_input("add_jccat_sort", MyBB::INPUT_INT)
      );
      $db->insert_query("jl_cat", $insert);
      redirect("misc.php?action=jobliste");
    }

    ///vorhandene Hauptkategorien bauen für tabs und select
    $get_cats = $db->simple_select("jl_maincat", "*", "", array('order_by' => 'jm_sort'));
    $hauptkategorie = "<select name=\"jobcat\" id=\"jobcat\">";
    while ($cat = $db->fetch_array($get_cats)) {
      $hauptkategorie .= "<option value=\"{$cat['jm_id']}\">{$cat['jm_title']}</option>";
    }
    $hauptkategorie .= "</select>";

    ///vorhandene Kategorien bauen für tabs und select
    $build_select = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_cat");
    $joblist_select_cat_add = "<select name=\"job_jc_cat\" id=\"job_jc_cat\">";
    $jc_selected = "";
    while ($jc_cat = $db->fetch_array($build_select)) {
      $maincat = $db->fetch_field($db->write_query("SELECT jm_title FROM " . TABLE_PREFIX . "jl_maincat WHERE jm_id = '{$jc_cat['jc_maincat']}'"), "jm_title");
      $joblist_select_cat_add .= "<option value=\"{$jc_cat['jc_id']}\">{$jc_cat['jc_title']} ({$maincat})</option>";
    }
    $joblist_select_cat_add .= "</select>";

    //Job/mögliche Arbeisstelle wird eingereicht
    if (isset($mybb->input['jobsubcat_send']) && $mybb->user['uid'] != 0) {
      //Moderatoren: Direkt angenommen
      if ($mybb->usergroup['canmodcp'] == 1) {
        $accepted = 1;
      } else {
        //nur user: muss noch von einem moderator akzeptiert werden
        $accepted = 0;
      }
      $insert = array(
        "js_mid" => $mybb->get_input("jobcat", MyBB::INPUT_INT),
        "js_title" => $db->escape_string($mybb->get_input("add_subcat_title", MyBB::INPUT_STRING)),
        "js_subtitle" => $db->escape_string($mybb->get_input("add_subcat_subtitle", MyBB::INPUT_STRING)),
        "js_subovercat" => $db->escape_string($mybb->get_input("job_jc_cat", MyBB::INPUT_STRING)),
        "js_descr" => $db->escape_string($mybb->get_input("add_subcat_descr", MyBB::INPUT_STRING)),
        "js_accepted" => $accepted,
        "js_sort" => $mybb->get_input("add_subcat_sort", MyBB::INPUT_INT),
        "js_user" => $mybb->user['uid'],
      );
      $db->insert_query("jl_subcat", $insert);
      redirect("misc.php?action=jobliste");
    }
    //löschen von usereinträgen
    if (isset($mybb->input['do_delete']) == "do_delete") {
      $uid = $mybb->get_input("uid", MyBB::INPUT_INT);
      $todel = $mybb->get_input("id", MyBB::INPUT_INT);
      //moderator oder der user
      if ($mybb->usergroup['canmodcp'] == 1 || $uid == $mybb->user['uid']) {
        $db->delete_query("jl_entry", "je_id = {$todel}");
        redirect("misc.php?action=jobliste");
      }
    }

    //job editieren - moderatoren oder der user der eingereicht hat
    if (isset($mybb->input['editjob']) == "editjob") {
      $uid = $mybb->get_input("e_uid", MyBB::INPUT_INT);
      $toedit = $mybb->get_input("e_jobid", MyBB::INPUT_INT);

      if ($mybb->usergroup['canmodcp'] == 1 || $uid == $mybb->user['uid']) {
        $update = array(
          "je_abteilung" => $mybb->get_input("e_jobabteilung", MyBB::INPUT_STRING),
          "je_position" => $mybb->get_input("e_jobposition", MyBB::INPUT_STRING),
          "je_sort" => $mybb->get_input("e_jobsort", MyBB::INPUT_INT),
          "je_profilstring" => $mybb->get_input("e_jobprofilstring", MyBB::INPUT_STRING),
        );
        $db->update_query("jl_entry", $update, "je_id = {$toedit}");
        redirect("misc.php?action=jobliste");
      }
    }

    //user hinzufügen
    if (isset($mybb->input['adduser'])) {

      if ($mybb->user['uid'] != 0) {
        $insert = array(
          "je_jsid" => $mybb->get_input("a_jobid", MyBB::INPUT_INT),
          "je_uid" => $mybb->get_input("a_uid", MyBB::INPUT_INT),
          "je_abteilung" => $db->escape_string($mybb->get_input("a_jobabteilung", MyBB::INPUT_STRING)),
          "je_position" => $db->escape_string($mybb->get_input("a_jobposition", MyBB::INPUT_STRING)),
          "je_sort" => $mybb->get_input("a_jobsort", MyBB::INPUT_INT),
          "je_profilstring" => $db->escape_string($mybb->get_input("a_profilstring", MyBB::INPUT_STRING)),
        );
        $db->insert_query("jl_entry", $insert);
        redirect("misc.php?action=jobliste");
      }
    }

    //Hauptkategorie editieren
    if (isset($mybb->input['editmaintitle'])) {
      $toedit = $mybb->get_input("jm_id", MyBB::INPUT_INT);

      if ($mybb->usergroup['canmodcp'] == 1) {
        $update = array(
          "jm_title" => $db->escape_string($mybb->get_input("jm_title", MyBB::INPUT_STRING)),
          "jm_subtitle" => $db->escape_string($mybb->get_input("jm_subtitle", MyBB::INPUT_STRING)),
          "jm_descr" => $db->escape_string($mybb->get_input("jm_descr", MyBB::INPUT_STRING)),
          "jm_sort" => $mybb->get_input("jm_sort", MyBB::INPUT_INT),
        );
        $db->update_query("jl_maincat", $update, "jm_id = '{$toedit}'");
        redirect("misc.php?action=jobliste");
      }
    }

    //Kategorie editieren
    if (isset($mybb->input['editcat'])) {
      $toedit = $mybb->get_input("jc_id", MyBB::INPUT_INT);


      if ($mybb->usergroup['canmodcp'] == 1) {
        $update = array(
          "jc_title" => $db->escape_string($mybb->get_input("jc_title", MyBB::INPUT_STRING)),
          "jc_sort" => $mybb->get_input("jc_sort", MyBB::INPUT_INT),
          "jc_maincat" => $mybb->get_input("jobcat_editmaincat", MyBB::INPUT_INT),
        );
        $db->update_query("jl_cat", $update, "jc_id = '{$toedit}'");
        redirect("misc.php?action=jobliste");
      }
    }

    //subkategorie/arbeitsstelle editieren
    if (isset($mybb->input['editsubtitle'])) {
      $toedit = $mybb->get_input("js_id", MyBB::INPUT_INT);
      if ($mybb->usergroup['canmodcp'] == 1) {
        $update = array(
          "js_title" => $mybb->get_input("js_title", MyBB::INPUT_STRING),
          "js_subtitle" => $mybb->get_input("js_subtitle", MyBB::INPUT_STRING),
          "js_subovercat" => $mybb->get_input("js_subovercat", MyBB::INPUT_STRING),
          "js_sort" => $mybb->get_input("js_sort", MyBB::INPUT_INT),
          "js_descr" => $mybb->get_input("js_descr", MyBB::INPUT_STRING),
        );
        $db->update_query("jl_subcat", $update, "js_id = {$toedit}");
        redirect("misc.php?action=jobliste");
      }
    }

    //Sachen löschen
    if (isset($mybb->input['deletesid']) && $mybb->usergroup['canmodcp'] == 1) {
      $db->delete_query("jl_subcat", "js_id = " . $mybb->get_input("deletesid", MyBB::INPUT_INT));
    }
    if (isset($mybb->input['deletecat']) && $mybb->usergroup['canmodcp'] == 1) {
      $db->delete_query("jl_cat", "jc_id = " . $mybb->get_input("deletecat", MyBB::INPUT_INT));
    }
    if (isset($mybb->input['deletemaincat']) && $mybb->usergroup['canmodcp'] == 1) {
      $db->delete_query("jl_maincat", "jm_id = " . $mybb->get_input("deletemaincat", MyBB::INPUT_INT));
    }

    //akzeptieren
    if (isset($mybb->input['accept'])) {
      $id = $mybb->get_input("accept", MyBB::INPUT_INT);
      if ($mybb->usergroup['canmodcp'] == 1) {
        $update = array(
          "js_accepted" => 1,
        );
        $db->update_query("jl_subcat", $update, "js_id = {$id}");
        redirect("misc.php?action=jobliste");
      }
    }

    /* AUSGABE */
    // Buttons für menü
    $get_maincats = $db->simple_select("jl_maincat", "*", "", array('order_by' => 'jm_sort, jm_title'));
    $counter = "";
    while ($maincat = $db->fetch_array($get_maincats)) {
      $descr = "<div class=\"joblist_descr\">" . $maincat['jm_descr'] . "</div>";

      if ($maincat['jm_subtitle'] != "") {
        eval("\$job_mainsubtitle = \"" . $templates->get("jobliste_mainsubtitle") . "\";");
      } else {
        $job_mainsubtitle = "";
      }
      $counter++;
      if ($counter == 1) {
        $default = "but_tabdefault";
      } else {
        $default = "";
      }
      $job_id = $maincat['jm_id'];
      $job_maintitle = $maincat['jm_title'];
      $job_mainbut = $maincat['jm_title'];
      //Bearbeiten Hauptkategorie
      // Nur Moderatoren können Hauptkategorien erstellen oder editieren
      if ($mybb->usergroup['canmodcp'] == 1) {
        eval("\$jobliste_editmaincat = \"" . $templates->get("jobliste_editmaincat") . "\";");
      } else {
        $jobliste_editmaincat = "";
      }

      //Tab Inhalt
      $jobliste_bit = "";

      //Kategorien holen
      $get_jc_cats = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_cat WHERE jc_maincat = {$maincat['jm_id']}");
      //Durchggehen und select bauen für editieren 
      while ($subcat_over = $db->fetch_array($get_jc_cats)) {
        $overcat_id = $subcat_over['jc_id'];

        ///vorhandene Hauptkategorien bauen für tabs und select
        $get_cats = $db->simple_select("jl_maincat", "*", "", array('order_by' => 'jm_sort'));
        $hauptkategorie_edit = "<select name=\"jobcat_editmaincat\" id=\"jobcat{$overcat_id}\">";
        while ($cat = $db->fetch_array($get_cats)) {
          if ($cat['jm_id'] == $subcat_over['jc_maincat']) {
            $selectedmain = " selected";
          } else {
            $selectedmain = "";
          }
          $hauptkategorie_edit .= "<option value=\"{$cat['jm_id']}\" {$selectedmain}>{$cat['jm_title']}</option>";
        }
        $hauptkategorie_edit .= "</select>";


        $overcat = $subcat_over['jc_title'];
        $overcat_sort = $subcat_over['jc_sort'];
        $jobliste_bit_edit_overcat = "";
        if ($mybb->usergroup['canmodcp'] == 1) {
          eval("\$jobliste_bit_edit_overcat .= \"" . $templates->get("jobliste_bit_edit_overcat") . "\";");
        } else {
          $jobliste_bit_edit_overcat = "";
        }

        //Die Arbeitsstellen/subkategorie
        $get_subcats = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_subcat WHERE js_subovercat = '{$overcat_id}' AND js_accepted = 1 ORDER BY js_subovercat, js_sort");
        $jobliste_bitsub = "";
        while ($subcat = $db->fetch_array($get_subcats)) {
          $anker = "sub" . $subcat['js_id'];
          $title_sub = $subcat['js_title'];
          $sid = $subcat['js_id'];
          //subtitle = Link zu einer Seite mit mehr infos
          if ($subcat['js_subtitle'] != "") {
            eval("\$subtitle = \"" . $templates->get("jobliste_bituserbit_subtitle") . "\";");
          } else {
            $subtitle = "";
          }
          //Wenn moderrator, kann explizit eine uid angegeben werden 
          if ($mybb->usergroup['canmodcp'] == 1) {
            $mod = "text";
          } else {
            $mod = "hidden";
          }

          //Bearbeiten der Arbeitsstellen // nur moderatoren
          if ($mybb->usergroup['canmodcp'] == 1) {
            $js_title = htmlspecialchars_uni($subcat['js_title']);
            $js_subtitle = htmlspecialchars_uni($subcat['js_subtitle']);

            $build_select = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_cat");
            $joblist_select_cat = "<select name=\"job_jc_cat\" id=\"job_jc_cat\">";
            $jc_selected = "";
            $maincat = "";
            while ($jc_cat = $db->fetch_array($build_select)) {
              $maincat = $db->fetch_field($db->write_query("SELECT jm_title FROM " . TABLE_PREFIX . "jl_maincat WHERE jm_id = '{$jc_cat['jc_maincat']}'"), "jm_title");

              if ($subcat['js_subovercat'] == $jc_cat['jc_id']) {
                $jc_selected = " SELECTED";
              } else {
                $jc_selected = "";
              }
              $joblist_select_cat .= "<option value=\"{$jc_cat['jc_id']}\" {$jc_selected}>{$jc_cat['jc_title']} ($maincat)</option>";
            }
            $joblist_select_cat .= "</select>";

            eval("\$jobliste_bitsub_edit = \"" . $templates->get("jobliste_bitsub_edit") . "\";");
          } else {
            $jobliste_bitsub_edit = "";
          }

          $js_descr = "<div class=\"job_subdescr\">{$subcat['js_descr']}</div>";
          if ($subcat['js_descr'] == "") {
            $js_descr = "";
          }

          $get_abteilung = $db->write_query("SELECT je_abteilung FROM " . TABLE_PREFIX . "jl_entry WHERE je_jsid = '" . $sid . "' GROUP BY je_abteilung ORDER BY je_abteilung, je_sort");
          $jobliste_bituser = "";
          //user einträge - dafür erst die Abteilungen, wenn welche angegeben wurden
          while ($abeilungen = $db->fetch_array($get_abteilung)) {
            $abteilung = $abeilungen['je_abteilung'];
            $get_jobsuser = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_entry WHERE je_jsid = '" . $sid . "' AND je_abteilung = '{$abteilung}' ORDER BY je_sort, je_position");
            $jobliste_bituserbit = "";
            $username = "";
            $edit = "";
            $delete = "";
            //die user
            while ($jobsuser = $db->fetch_array($get_jobsuser)) {
              $je_id = $jobsuser['je_id'];
              $userarray = get_user($jobsuser['je_uid']);
              if ($jobsuser['je_position'] == "") {
                $position = "";
              } else {
                $position = "({$jobsuser['je_position']})";
              }
              $uid = $jobsuser['je_uid'];
              if ($uid  == 0) {
                $username = "";
                $edit = "";
                $delete = "";
              } else {
                $username = build_profile_link($userarray['username'], $userarray['uid']);
                // wenn moderator oder der gleiche user, kann man sich austragen oder editieren
                if (($mybb->usergroup['canmodcp'] == 1 || $uid == $mybb->user['uid'])) {
                  $edit = "<a onclick=\"$('#cedit{$je_id}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[e]</a>";
                  $delete =  "<a href=\"misc.php?action=jobliste&do_delete=do_delete&id={$je_id}&uid={$uid}\" onClick=\"return confirm('Möchtest du den Eintrag wirklich löschen?');\">[x]</a>";
                  eval("\$jobliste_bituserbit_edit = \"" . $templates->get("jobliste_bituserbit_edit") . "\";");
                } else {
                  $edit = "";
                  $delete = "";
                  $jobliste_bituserbit_edit = "";
                }
              }
              eval("\$jobliste_bituserbit .= \"" . $templates->get("jobliste_bituserbit") . "\";");
            }
            eval("\$jobliste_bituser .= \"" . $templates->get("jobliste_bituser") . "\";");
          }
          //wenn kein Gast, kann man sich eintragen.
          if ($mybb->user['uid'] != 0) {
            //user dürfen sich selbst eintragen - oder moderatotr
            if ($mybb->settings['jobliste_mem_self'] == 1 || $mybb->usergroup['canmodcp'] == 1) {
              eval("\$addjob = \"" . $templates->get("jobliste_bitsub_add") . "\";");
            } else {
              $addjob = "";
            }
          } else {
            $addjob = "";
          }
          eval("\$jobliste_bitsub .= \"" . $templates->get("jobliste_bitsub") . "\";");
        }

        eval("\$jobliste_bit .= \"" . $templates->get("jobliste_bit") . "\";");
      }
      eval("\$jobliste_typ .= \"" . $templates->get("jobliste_typ") . "\";");

      if ($mybb->settings['jobliste_tabs'] == 1) {
        eval("\$jobliste_tabbit .= \"" . $templates->get("jobliste_tabbit") . "\";");
      } else {
        eval("\$jobliste_tabbit .= \"" . $templates->get("jobliste_maincat_links") . "\";");
      }
    }

    $get_subcats_not = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_subcat WHERE js_accepted = 0 ORDER BY js_sort");
    $jobliste_bitsub = "";
    while ($subcat_not = $db->fetch_array($get_subcats_not)) {
      $anker = "mod" . $subcat_not['js_id'];
      $sid = $subcat_not['js_id'];
      $overcattitle = $db->fetch_field($db->simple_select("jl_maincat", "*", "jm_id = {$subcat_not['js_mid']}"), "jm_title");
      $fromuser = get_user($subcat_not['js_user']);
      eval("\$jobliste_modbit .= \"" . $templates->get("jobliste_modbit") . "\";");
    }

    //formular Subkategorie/Arbeitsstelle
    if ($mybb->user['uid'] != 0) {
      if ($mybb->settings['jobliste_mem_addSubcat'] == 1 || $mybb->usergroup['canmodcp'] == 1) {
        eval("\$jobliste_addsubcat .= \"" . $templates->get("jobliste_addsubcat") . "\";");
      } else {
        $jobliste_addsubcat = "<br/>";
      }
    } else {
      $jobliste_addsubcat = "<br/>";
    }

    //Hinzufügen Kategorie
    if ($mybb->usergroup['canmodcp'] == 1) {
      eval("\$jobliste_addcat = \"" . $templates->get("jobliste_addcat") . "\";");
    } else {
      $jobliste_addcat = "";
    }

    //javascript for tabbing
    if ($mybb->settings['jobliste_tabs'] == 1) {
      eval("\$jobliste_tab_js = \"" . $templates->get("jobliste_tab_js") . "\";");
    }

    //Moderationsstuff
    if ($mybb->usergroup['canmodcp'] == 1 && $db->num_rows($get_subcats_not) > 0) {
      eval("\$joblist_modstuff .= \"" . $templates->get("jobliste_mod") . "\";");
    }

    eval('$page = "' . $templates->get('jobliste_main') . '";');
    output_page($page);

    die();
  }
}

/*Index Anzeige für job annehmen / ablehnen */
$plugins->add_hook('index_start', 'jobliste_modalert');
function jobliste_modalert()
{
  global $mybb, $db, $templates, $jobliste_indexmod;
  if ($mybb->usergroup['canmodcp'] == 1) {

    $get_subcats_not = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "jl_subcat WHERE js_accepted = 0 ORDER BY js_sort");
    if ($db->num_rows($get_subcats_not) > 0) {
      while ($subcat_not = $db->fetch_array($get_subcats_not)) {
        $anker = "mod" . $subcat_not['js_id'];
        $sid = $subcat_not['js_id'];
        $overcattitle = $db->fetch_field($db->simple_select("jl_maincat", "*", "jm_id = {$subcat_not['js_mid']}"), "jm_title");
        $fromuser = get_user($subcat_not['js_user']);
        eval("\$jobliste_indexmodbit .= \"" . $templates->get("jobliste_indexmodbit") . "\";");
      }
      eval("\$jobliste_indexmod = \"" . $templates->get("jobliste_indexmod") . "\";");
    }
  }
}


/*#######################################
#Hilfsfunktion für Mehrfachcharaktere (accountswitcher)
#Alle angehangenen Charas holen
#an die Funktion übergeben: Wer ist Online, die dazugehörige accountswitcher ID (ID des Hauptcharas) 
######################################*/
function jobliste_get_allchars($thisuser)
{
  global $mybb, $db;
  //wir brauchen die id des Hauptcharas
  $as_uid = $mybb->user['as_uid'];
  $charas = array();
  if ($as_uid == 0) {
    // as_uid = 0 wenn hauptaccount oder keiner angehangen
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $thisuser) OR (uid = $thisuser) ORDER BY username");
  } else if ($as_uid != 0) {
    //id des users holen wo alle an gehangen sind 
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $as_uid) OR (uid = $thisuser) OR (uid = $as_uid) ORDER BY username");
  }
  while ($users = $db->fetch_array($get_all_users)) {

    $uid = $users['uid'];
    $charas[$uid] = $users['username'];
  }
  return $charas;
}


/**
 * Was passiert wenn ein User gelöscht wird
 * Einträge aus jobliste löschen
 */
$plugins->add_hook("admin_user_users_delete_commit_end", "jobliste_userdelete");
function jobliste_userdelete()
{
  global $db, $cache, $mybb, $user;
  $todelete = (int)$user['uid'];
  $db->delete_query('jl_entry', "je_uid = " . (int)$user['uid'] . "");
}


// ONLINE ANZEIGE - WER IST WO
$plugins->add_hook("fetch_wol_activity_end", "jobliste_online_activity");
function jobliste_online_activity($user_activity)
{

  global $parameters, $user;

  $split_loc = explode(".php", $user_activity['location']);
  if ($split_loc[0] == $user['location']) {
    $filename = '';
  } else {
    $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
  }

  switch ($filename) {
    case 'misc':
      if ($parameters['action'] == "jobliste") {
        $user_activity['activity'] = "jobliste";
      }
      break;
  }
  return $user_activity;
}

$plugins->add_hook("build_friendly_wol_location_end", "jobliste_online_location");
function jobliste_online_location($plugin_array)
{

  global $mybb, $theme, $lang;

  if ($plugin_array['user_activity']['activity'] == "jobliste") {
    $plugin_array['location_name'] = "Sieht sich die <a href=\"misc.php?action=jobliste\">Jobliste</a> an.";
  }


  return $plugin_array;
}


/*Install Funktionen*/
function jobliste_database($type = "install")
{
  global $db;
  // Erstellen der Tabellen
  // Die übergeordneten Gruppen 
  if (!$db->table_exists("jl_maincat")) {
    $db->write_query("CREATE TABLE " . TABLE_PREFIX . "jl_maincat (
    `jm_id` int(11) NOT NULL AUTO_INCREMENT,
    `jm_title` varchar(200) NOT NULL,
    `jm_subtitle` varchar(200) NOT NULL,
    `jm_descr` VARCHAR(2000) NOT NULL,
    `jm_sort` int(10) NOT NULL DEFAULT 0,
    PRIMARY KEY (`jm_id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }

  // Die Kategorien innerhalb der Gruppen 
  if (!$db->table_exists("jl_cat")) {
    $db->write_query("CREATE TABLE " . TABLE_PREFIX . "jl_cat (
    `jc_id` int(11) NOT NULL AUTO_INCREMENT,
    `jc_title` varchar(200) NOT NULL,
    `jc_maincat` varchar(200) NOT NULL,
    `jc_sort` int(10) NOT NULL DEFAULT 0,
    PRIMARY KEY (`jc_id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }

  //Deren untergerdneten Jobs
  if (!$db->table_exists("jl_subcat")) {
    $db->write_query("CREATE TABLE " . TABLE_PREFIX . "jl_subcat (
    `js_id` int(11) NOT NULL AUTO_INCREMENT,
    `js_mid` int(11) NOT NULL,
    `js_title` varchar(200) NOT NULL,
    `js_subtitle` varchar(200) NOT NULL,
    `js_subovercat` varchar(200) NOT NULL,
    `js_descr` varchar(200) NOT NULL,
    `js_accepted` int(1) NOT NULL DEFAULT 0,
    `js_sort` int(10) NOT NULL DEFAULT 0,
    `js_user` int(10) NOT NULL,
    PRIMARY KEY (`js_id`)
     ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }

  // Tabelle für user einträge
  if (!$db->table_exists("jl_entry")) {
    $db->write_query("CREATE TABLE " . TABLE_PREFIX . "jl_entry (
    `je_id` int(11) NOT NULL AUTO_INCREMENT,
    `je_jsid` int(11) NOT NULL,
    `je_uid` int(11) NOT NULL,
    `je_abteilung` varchar(255) NOT NULL,
    `je_position` varchar(255) NOT NULL,
    `je_sort` int(10) NOT NULL,
    `je_profilstring` varchar(255) NOT NULL,
    PRIMARY KEY (`je_id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
  }
}

function jobliste_setting_array()
{
  $setting_array = array(
    'jobliste_mem_addSubcat' => array(
      'title' => 'Arbeitsstellen/Subkategorie',
      'description' => 'Dürfen Mitglieder neue Arbeitsstellen in die Jobliste hinzufügen?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    ),
    'jobliste_mem_self' => array(
      'title' => 'Eintragen',
      'description' => 'Dürfen Mitglieder sich selbst eintragen?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    ),
    'jobliste_tabs' => array(
      'title' => 'Darstellung der Hauptkategorien',
      'description' => 'Sollen die Hauptategorien als Tabs dargestellt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    )
  );
  return $setting_array;
}

function jobliste_add_settings($type = "install")
{
  global $db;

  if ($type == 'install') {
    // Admin Einstellungen
    $setting_group = array(
      'name' => 'jobliste',
      'title' => 'Jobliste',
      'description' => 'Einstellungen für die Jobliste.',
      'disporder' => 8, // The order your setting group will display
      'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);
  } else {
    $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'jobliste%' LIMIT 1;"), "gid");
  }

  $setting_array = jobliste_setting_array();

  if ($type == 'install') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
      $db->insert_query('settings', $setting);
    }
  }

  if ($type == 'update') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;

      //alte einstellung aus der db holen
      $check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check = $db->num_rows($check);

      if ($check == 0) {
        $db->insert_query('settings', $setting);
        echo "Setting: {$name} wurde hinzugefügt.";
      } else {

        //die einstellung gibt es schon, wir testen ob etwas verändert wurde
        while ($setting_old = $db->fetch_array($check2)) {
          if (
            $setting_old['title'] != $setting['title'] ||
            $setting_old['description'] != $setting['description'] ||
            $setting_old['optionscode'] != $setting['optionscode'] ||
            $setting_old['disporder'] != $setting['disporder']
          ) {
            //wir wollen den value nicht überspeichern, also nur die anderen werte aktualisieren
            $update_array = array(
              'title' => $setting['title'],
              'description' => $setting['description'],
              'optionscode' => $setting['optionscode'],
              'disporder' => $setting['disporder']
            );
            $db->update_query('settings', $update_array, "name='{$name}'");
            echo "Setting: {$name} wurde aktualisiert.<br>";
          }
        }
      }
    }
  }
  rebuild_settings();
}


function jobliste_add_templates($type = 'install')
{
  global $db;
  $templates = array();
  //add templates and stylesheets
  // Add templategroup
  //templategruppe nur beim installieren hinzufügen
  if ($type == 'install') {
    $templategrouparray = array(
      'prefix' => 'jobliste',
      'title'  => $db->escape_string('Jobliste'),
      'isdefault' => 1
    );
    $db->insert_query("templategroups", $templategrouparray);
  }

  //Templates erstellen
  $templates[] = array(
    "title" => 'jobliste_main',
    "template" => '<html>
          <head>
            <title>{$mybb->settings[\\\'bbname\\\']} - Jobliste</title>
            {$headerinclude}
          </head>
          <body>
            {$header}
            <table width="100%" cellspacing="5" cellpadding="5" class="tborder jobliste">
              <tr>
                <td valign="top">
                  <div class="jobliste__title"><h2>Jobliste</h2></div>
                  <div class="jobliste__descr">Hier findest du eine Übersicht über mögliche Berufe, sowohl Informationen zu
                    den einzelnen Arbeitstellen. Das Ganze kann auch von euch ergänzt werden. 
                  </div>
                  <div class="jobliste__forms">
                    {$jobliste_addcat_mods}
                    {$jobliste_addcat}
                    {$jobliste_addsubcat}
                  </div>

                  <div class="jobliste__tabnav res_tab">
                    {$jobliste_tabbit}
                  </div>
                  {$jobliste_typ}
                </td>
              </tr>
            </table>
            {$joblist_modstuff}
            {$jobliste_tab_js}
            {$footer}

          </body>
        </html>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_addcat_mods',
    "template" => '<div class="jobliste">
        <a onclick="$(\\\'#jobliste__mainmodul\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[add Hauptkategorie]</a>
        <div class="modal" id="jobliste__mainmodul" style="display: none; padding: 10px; margin: auto; text-align: center;">
          <div class="jobadd_select jobadd__item">
            <h3>Hauptkategorie hinzufügen</h3>
            <form id="addcat" method="post" action="misc.php?action=jobliste">
              <div class="joblist__formitem kat">
                <label for="add_cat_title">Titel der Hauptkategorie</label><br>
                <input type="text" placeholder="Hauptkategorie Title" name="add_cat_title" id="add_cat_title" required/>
              </div>
              <div class="joblist__formitem subkat">
                <label for="add_cat_subtitle">Subtitel der Kategorie</label><br>
                <input type="text" placeholder="Kategorie Untertitel" name="add_cat_subtitle" id="add_cat_subtitle"/>
              </div>
              <div class="joblist__formitem sort">
                <label for="add_cat_sort">Anzeigenreihenfolge</label><br>
                <input type="number" placeholder="Darstellungsreihenfolge" name="add_cat_sort" id="add_cat_sort"/>
              </div>
              <div class="joblist__formitem descr">
                <label for="anschlusstxt">Beschreibung</label><br>
                <textarea placeholder="Hier die Beschreibung zur Kategorie." name="add_cat_descr" id="anschlusstxt" cols="40" rows="3"></textarea>
              </div>
                <div class="joblist__formitem descr">
                  <button class="bl-btn" type="submit" value="Submit" name="jobcat_send">Submit</button>
                </div>
                </form>
              
          </div>
        </div>
      </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_editmaincat',
    "template" => '<span class="smalltext">
          <a onclick="$(\\\'#maincatedit{$job_id}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[e]</a>
          <a href="misc.php?action=jobliste&deletemaincat={$job_id}" onClick="return confirm(\\\'Möchtest du die Kategorie wirklich löschen? Dann werden auch die zugeordnten Jobs nicht mehr angezeigt!\\\');">[d]</a>
          </span>
          <div class="modal editscname" id="maincatedit{$job_id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
          <form action="misc.php?action=jobliste" id="formeditmain{$job_id}" method="post" >
            <div class="joblist__formitem title">
            <input type="hidden" value="{$job_id}" name="jm_id"/>
            <label for="jm_title{$job_id}" >Titel</label><br>
            <input type="text" value="{$maincat[\\\'jm_title\\\']}" name="jm_title" id="jm_title{$job_id}" />
            </div>
            <div class="joblist__formitem extra">
            <label for="jm_subtitle{$job_id}">Subtitel</label><br>
            <input type="text" value="{$maincat[\\\'jm_subtitle\\\']}" name="jm_subtitle" id="jm_subtitle{$job_id}" />
            </div>
            <div class="joblist__formitem sort">
            <label for="jm_sort{$job_id}" >Anzeigenreihenfolge</label><br>
            <input type="text" value="{$maincat[\\\'jm_sort\\\']}" name="jm_sort" id="jm_sort{$job_id}"/>
            </div>
            <div class="joblist__formitem descr">
            <label for="jm_descr{$job_id}" >Beschreibung</label><br>
            <textarea name="jm_descr" cols="40" rows="3" id="jm_descr{$job_id}">{$maincat[\\\'jm_descr\\\']}</textarea>
            </div>
            <div class="joblist__formitem send">
            <button type="submit" name="editmaintitle">Submit</button>
            </div>
          </form>
        </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bit_edit_overcat',
    "template" => '<span class="smalltext">
              <a onclick="$(\\\'#subcatedit_jc{$overcat_id}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[e]</a> 
                <a href="misc.php?action=jobliste&deletecat={$overcat_id}" onClick="return confirm(\\\'Möchtest du die Kategorie wirklich löschen? Dann werden auch die zugeordnten Jobs nicht mehr angezeigt!\\\');">[d]</a>
            </span>
            <div class="modal editscname" id="subcatedit_jc{$overcat_id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
              <form action="misc.php?action=jobliste" id="formeditcat{$overcat_id}" method="post" >
                <div class="joblist__formitem">	
                  <input type="hidden" value="{$overcat_id}" name="jc_id">
                  <label for="jc_title{$overcat_id}">Name</label><br>
                  <input type="text" value="{$overcat}" name="jc_title" id="jc_title{$overcat_id}" />
                </div>
                <div class="joblist__formitem">	
                  <label for="jc_sort{$overcat_id}">Anzeigenreihenfolge</label><br>
                  <input type="number" value="{$overcat_sort}" name="jc_sort"  id="jc_sort{$overcat_id}" />
                </div>
              <div class ="joblist__formitem">
                <input form="formeditcat{$overcat_id}" type="submit" name="editcat" value="Speichern" />
              </div>
              </form>
            </div>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bituserbit_subtitle',
    "template" => '<div class="jl_subtitle"><a href="{$subcat[\\\'js_subtitle\\\']}">[klick für mehr Infos]</a></div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bitsub_edit',
    "template" => '<span class="smalltext">
	<a onclick="$(\\\'#subcatedit{$sid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[e]</a> 
	<a href="misc.php?action=jobliste&deletesid={$sid}" onClick="return confirm(\\\'Möchtest du den Eintrag wirklich löschen?\\\');">[d]</a>
</span>

<div class="modal editscname" id="subcatedit{$sid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
	<form action="misc.php?action=jobliste" id="formeditsub{$sid}" method="post" >
		<div class ="joblist__formitem">	
			<input type="hidden" value="{$sid}" name="js_id">
			<label for="js_title{$sid}">Name</label><br>
      <input type="text" value="{$js_title}" name="js_title"  id="js_title{$sid}" />
		</div>
		<div class="joblist__formitem">
			<label for="js_subtitle{$sid}">Link (nur Url)</label><br>
			<input type="url" value="{$js_subtitle}" name="js_subtitle" id="js_subtitle{$sid}" />
		</div>
		<div class="joblist__formitem">
			<label for="js_sort{$sid}">Reihenfolge</label><br>
			<input type="number" value="{$subcat[\\\'js_sort\\\']}" name="js_sort" id="js_sort{$sid}" />
		</div>
		<div class="joblist__formitem">
			<label>Kategorie</label><br>
			{$joblist_select_cat}
		</div>
		<div class="joblist__formitem">
			<label for="js_descr{$sid}">Beschreibung</label><br>
			<textarea  name="js_descr" id="js_descr{$sid}" />{$subcat[\\\'js_descr\\\']}</textarea>
		</div>
		<div class ="joblist__formitem">
			<input form="formeditsub{$sid}" type="submit" name="editsubtitle" value="Speichern" />
		</div>
	</form>
</div>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bituserbit_edit',
    "template" => '{$edit} {$delete} 
            <div class="modal editscname" id="cedit{$je_id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
              <form action="misc.php?action=jobliste" id="formeditjob{$je_id}" method="post" >
                <div class ="joblist__formitem">	
                  <input type="hidden" value="{$je_id}" name="e_jobid">
                  <input type="hidden" value="{$uid}" name="e_uid">
                  <label for="e_jobabteilung{$je_id}">Abteilung</label>
                  <input type="text" value="{$jobsuser[\\\'je_abteilung\\\']}" name="e_jobabteilung" id="e_jobabteilung{$je_id}" />
                </div>
                <div class ="joblist__formitem">
                  <label for="e_jobposition{$je_id}">Position</label>
                  <input type="text" value="{$jobsuser[\\\'je_position\\\']}" name="e_jobposition"  id="e_jobposition{$je_id}" />
                </div>
                <div class ="joblist__formitem">
                  <label for="e_jobposition{$je_id}">Sortierung</label>
                  <input type="text" value="{$jobsuser[\\\'je_sort\\\']}" name="e_jobsort" id="e_jobsort{$je_id}" />
                </div>
                <div class ="joblist__formitem">
                  <input form="formeditjob{$je_id}" type="submit" name="editjob" value="Speichern" />
                </div>
              </form>
            </div>
        ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bituserbit',
    "template" => '<div class="jobuser-entry__item">{$username} {$position} {$jobliste_bituserbit_edit}</div>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bituser',
    "template" => '<div class="jobliste-user__item jobuser">
        <div class="jobuser__item abteilung">{$abteilung}</div>
        <div class="jobuser__item jobuser-entry">{$jobliste_bituserbit}</div>
        </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bitsub_add',
    "template" => '<i class="fa-solid fa-user-plus"></i> <a onclick="$(\\\'#useradd{$sid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[add]</a>
        <div class="modal editscname" id="useradd{$sid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
          <form action="misc.php?action=jobliste" id="formaddjob{$sid}" method="post" >
            <div class ="joblist__formitem">	
              <input type="hidden" value="{$sid}" name="a_jobid">
              <input type="{$mod}" value="{$thisuser}" name="a_uid"><br>
              <label for="jobabteilung{$sid}">Abteilung</label><br>
              <input type="text" placeholder="z.B Mitarbeiter" name="a_jobabteilung" id="jobabteilung{$sid}" />
            </div>
            <div class ="joblist__formitem">
              <label for="jobposition{$sid}">Position:</label><br>
              <input type="text" placeholder="z.B 3. Lehrjahr" name="a_jobposition" id="jobposition{$sid}" />
            </div>
            <div class ="joblist__formitem">
              <label for="jobsort{$sid}">Sortierung:</label><br>
              <input type="numbers" placeholder="1" name="a_jobsort" id ="jobsort{$sid}"/>
            </div>
            <div class ="joblist__formitem">
              <input form="formaddjob{$sid}" type="submit" name="adduser" value="Speichern" />
            </div>
          </form>
        </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bitsub',
    "template" => '<div class="job_bit__item">
            <h3 class="job_bit__title">{$title_sub}{$jobliste_bitsub_edit}</h3>
            {$subtitle}
            {$js_descr}
            <div class="jobliste-user">
              {$jobliste_bituser}
            </div>
            <div class="jobliste-user adduser">
              {$addjob}
            </div>
          </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_bit',
    "template" => '<div class="job_bit">
        <h2 class="job_bit__heading">{$overcat}{$jobliste_bit_edit_overcat}</h2>
        {$jobliste_bitsub}
      </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );


  $templates[] = array(
    "title" => 'jobliste_typ',
    "template" => '<div class="job_show jtabcontent" id="tab_{$job_id}">
          <div class="job_ausgabe">
            <h2 class="job__title">{$job_maintitle}{$jobliste_editmaincat}</h2>
            {$job_mainsubtitle}
            {$descr}
            {$jobliste_bit}
          </div>
          {$jobliste_add}
        </div>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_typ',
    "template" => '<button class="job_tablinks bl-btn" onclick="openJobid(event, \\\'tab_{$job_id}\\\')"  id="{$default}" >{$job_maintitle}</button>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_modbit',
    "template" => '<div class="job_show_mod__item">
      <a id="{$anker}"></a>Eintrag für <strong>{$overcattitle}</strong> von {$fromuser[\\\'username\\\']}.<br/>
      <strong>{$subcat_not[\\\'js_title\\\']}</strong> - {$subcat_not[\\\'js_subovercat\\\']} - <i>{$subcat_not[\\\'js_subtitle\\\']}</i><br/>
      <p>{$subcat_not[\\\'js_descr\\\']}</p>
      <a href="misc.php?action=jobliste&accept={$sid}">[freischalten]</a> <a onclick="$(\\\'#modsubcatedit{$sid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[editieren]</a> <a href="private.php?action=send&uid={$subcat_not[\\\'js_user\\\']}">[PN an User]</a>
      </div>
      <div class="modal editscname" id="modsubcatedit{$sid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
      <form action="misc.php?action=jobliste" id="formeditmodsub{$sid}" method="post" >
        <div class ="joblist__formitem">
          <input type="hidden" value="{$sid}" name="js_id">
          <label for="js_title{$sid}">Titel</label><br>
          <input type="text" value="{$subcat_not[\\\'js_title\\\']}" name="js_title" id="js_title{$sid}" />
        </div>
        <div class ="joblist__formitem"> 
          <label for="js_subtitle{$sid}">Subtitle</label><br>
          <input type="text" value="{$subcat_not[\\\'js_subtitle\\\']}" name="js_subtitle" id="js_subtitle{$sid}" />
        </div>
        <div class ="joblist__formitem">
          <label for="js_sort{$sid}">Reihenfolge</label><br>
          <input type="number" value="{$subcat_not[\\\'js_sort\\\']}" name="js_sort" id="js_sort{$sid}" />
        </div>
        <div class ="joblist__formitem">  
          <label for="js_subovercat{$sid}">Überkategorie</label><br>
          <input type="text" value="{$subcat_not[\\\'js_subovercat\\\']}" name="js_subovercat" id="js_subovercat{$sid}" />
        </div>
        <div class ="joblist__formitem">
          <label for="js_descr{$sid}">Beschreibung</label<br>
          <textarea name="js_descr" id="js_descr{$sid}" />{$subcat_not[\\\'js_descr\\\']}</textarea>
        </div>
      <div class ="jobentry_editform">
        <input form="formeditmodsub{$sid}" type="submit" name="editsubtitle" value="Speichern" />
      </div>
      </form>
      </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_addsubcat',
    "template" => '<div class="jobadd">
              <a onclick="$(\\\'#divaddsubcat\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[Arbeitsstelle hinzufügen]</a>

                <div class="jobadd_select jobadd__item">
                  <div class="modal" id="divaddsubcat" style="display: none; padding: 10px; margin: auto; text-align: center;">
                    <form id="addsubcat" method="post" action="misc.php?action=jobliste">
                      <div class="joblist__formitem kat">
                        <label for="add_oversubcat_subtitle" for="jobcat">Haupt Kategorie</label><br>
                        {$hauptkategorie}
                      </div>
                      <div class="joblist__formitem subkat">
                        <label for="add_oversubcat_subtitle">Kategorie</label><br>
                        {$joblist_select_cat_add} 
                        </div>
                      <div class="joblist__formitem bezeichnung">
                        <label for="add_subcat_title">Name/Bezeichnung</label><br>
                        <input type="text" placeholder="Restaurant Al Dente" name="add_subcat_title" id="add_subcat_title" />
                      </div>
                      <div class="joblist__formitem link">
                        <label for="add_subcat_subtitle">Extrainfo</label><br>
                        <input type="text" placeholder="https://" name="add_subcat_subtitle" id="add_subcat_subtitle" />
                      </div>
                      <div class="joblist__formitem sort">
                        <label for="add_subcat_sort">Sortierung</label><br>
                        <input type="number" placeholder="Darstellungsreihenfolge" name="add_subcat_sort" id="add_subcat_sort" />
                      </div>
                      <div class="joblist__formitem descr">
                        <label for="anschlusstxt">Beschreibung</label><br>
                        <textarea placeholder="Gemütliches italienisches Restaurant." name="add_subcat_descr" id="anschlusstxt" cols="40" rows="3"></textarea>
                      </div>
                      <div class="joblist__formitem send">
                            <button class="bl-btn" type="submit" form="addsubcat" value="Submit" name="jobsubcat_send">Submit</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
    ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_addcat',
    "template" => '<div class="jobadd">
            <a onclick="$(\\\'#add_jc_cat\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[Kategorie hinzufügen]</a>

            <div class="jobadd_select jobadd__item">
              <div class="modal" id="add_jc_cat" style="display: none; padding: 10px; margin: auto; text-align: center;">
                <form id="addcat" method="post" action="misc.php?action=jobliste">
                  <div class="joblist__formitem kat">
                    <label for="add_oversubcat_subtitle" for="jobcat">Mainkategorie</label>
                    {$hauptkategorie}
                  </div>
                  <div class="joblist__formitem bezeichnung">
                    <label for="add_cat_title">Sub-Kategorie</label>
                    <input type="text" placeholder="Gastronomie" name="add_jccat_title" id="add_cat_title" />
                  </div>
                  <div class="joblist__formitem bezeichnung">
                    <label for="add_jccat_sort">Anzeigenreihenfolge</label>
                    <input type="number" placeholder="0" name="add_jccat_sort" id="add_jccat_sort" />
                  </div>
                  <div class="joblist__formitem sendbutton">
                    <button class="bl-btn" type="submit" value="Submit" name="jobcat_jc_send">Submit</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
    ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_tab_js',
    "template" => '<script>
          function openJobid(evt, jobid) {
            // Declare all variables
            var i, jtabcontent, job_tablinks;

            // Get all elements with class="tabcontent" and hide them
            jtabcontent = document.getElementsByClassName("jtabcontent");
            for (i = 0; i < jtabcontent.length; i++) {
              jtabcontent[i].style.display = "none";
            }

            // Get all elements with class="tablinks" and remove the class "active"
            job_tablinks = document.getElementsByClassName("job_tablinks");
            for (i = 0; i < job_tablinks.length; i++) {
              job_tablinks[i].className = job_tablinks[i].className.replace(" active", "");
            }

            // Show the current tab, and add an "active" class to the button that opened the tab
            document.getElementById(jobid).style.display = "block";
            evt.currentTarget.className += " active";
          }

          // Get the element with id="defaultOpen" and click on it
          document.getElementById("but_tabdefault").click();
        </script>
    ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_mod',
    "template" => '<div class="job_show_mod__item">
            <a id="{$anker}"></a>Eintrag für <strong>{$overcattitle}</strong> von {$fromuser[\\\'username\\\']}.<br/>
            <strong>{$subcat_not[\\\'js_title\\\']}</strong> - {$subcat_not[\\\'js_subovercat\\\']} - <i>{$subcat_not[\\\'js_subtitle\\\']}</i><br/>
            <p>{$subcat_not[\\\'js_descr\\\']}</p>
            <a href="misc.php?action=jobliste&accept={$sid}">[freischalten]</a> <a onclick="$(\\\'#modsubcatedit{$sid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[editieren]</a> 
            <a href="private.php?action=send&uid={$subcat_not[\\\'js_user\\\']}">[PN an User]</a>
          </div>
          <div class="modal editscname" id="modsubcatedit{$sid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
            <form action="misc.php?action=jobliste" id="formeditmodsub{$sid}" method="post" >
              <div class ="joblist__formitem">
                <input type="hidden" value="{$sid}" name="js_id">
                <label for="js_title{$sid}">Titel</label><br>
                <input type="text" value="{$subcat_not[\\\'js_title\\\']}" name="js_title" id="js_title{$sid}" />
              </div>
              <div class ="joblist__formitem"> 
                <label for="js_subtitle{$sid}">Subtitle</label><br>
                <input type="text" value="{$subcat_not[\\\'js_subtitle\\\']}" name="js_subtitle" id="js_subtitle{$sid}" />
              </div>
              <div class ="joblist__formitem">
                <label for="js_sort{$sid}">Reihenfolge</label><br>
                <input type="number" value="{$subcat_not[\\\'js_sort\\\']}" name="js_sort" id="js_sort{$sid}" />
              </div>
              <div class ="joblist__formitem">  
                <label for="js_subovercat{$sid}">Überkategorie</label><br>
                <input type="text" value="{$subcat_not[\\\'js_subovercat\\\']}" name="js_subovercat" id="js_subovercat{$sid}" />
              </div>
              <div class ="joblist__formitem">
                <label for="js_descr{$sid}">Beschreibung</label<br>
                  <textarea name="js_descr" id="js_descr{$sid}" />{$subcat_not[\\\'js_descr\\\']}</textarea>
              </div>
              <div class ="jobentry_editform">
                <input form="formeditmodsub{$sid}" type="submit" name="editsubtitle" value="Speichern" />
              </div>
            </form>
          </div>
        ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_indexmodbit',
    "template" => '<div class="scenetracker_reminder item"><span>Eintrag für <strong>{$overcattitle}</strong> von {$fromuser[\\\'username\\\']}. <br>
	<a href="misc.php?action=jobliste#{$anker}">[überprüfen und freischalten]</a></span>
</div>
  ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_indexmod',
    "template" => '<div class="reservations_index pm_alert">
        <strong>Neue Eintrag Jobliste:</strong>
          {$jobliste_indexmodbit} 
      </div>
      ',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_tabbit',
    "template" => '<button class="job_tablinks " onclick="openJobid(event, \\\'tab_{$job_id}\\\')"  id="{$default}" >{$job_maintitle}</button>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_maincat_links',
    "template" => '<a class="job_tablinks" href="#tab_{$job_id}" >{$job_maintitle}</a>',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  $templates[] = array(
    "title" => 'jobliste_mainsubtitle',
    "template" => '{$maincat[\\\'jm_subtitle\\\']}',
    "sid" => "-2",
    "version" => "",
    "dateline" => TIME_NOW
  );

  if ($type == 'update') {
    foreach ($templates as $template) {
      $query = $db->simple_select("templates", "tid, template", "title = '" . $template['title'] . "' AND sid = '-2'");
      $existing_template = $db->fetch_array($query);

      if ($existing_template) {
        if ($existing_template['template'] !== $template['template']) {
          $db->update_query("templates", array(
            'template' => $template['template'],
            'dateline' => TIME_NOW
          ), "tid = '" . $existing_template['tid'] . "'");
        }
      } else {
        $db->insert_query("templates", $template);
      }
    }
  } else {

    foreach ($templates as $template) {
      $check = $db->num_rows($db->simple_select("templates", "title", "title = '" . $template['title'] . "'"));
      if ($check == 0) {
        $db->insert_query("templates", $template);
      }
    }
  }
}

function jobliste_stylesheet()
{
  global $db;
  $css = array();
  $css = array(
    'name' => 'jobliste.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" =>    '
          .jobliste__title {
              text-align: center;
          }

          .job__mainsubtitle {
              text-align: center;
          }
          .jobliste__tabnav.res_tab {
              display: flex;
              flex-wrap: wrap;
              gap: 10px;
              justify-content: center;
              padding: 10px;
          }

          .jobliste__forms {
              margin: 10px;
              display: flex;
              flex-wrap: wrap;
              gap: 10px;
              justify-content: center;
          }

          .job_ausgabe .joblist_descr {
              padding: 5px 20px;
          }

          .job_bit__heading {
              border-bottom: 1px solid;
          }

          .job_ausgabe .job__title {
              text-align: center;
          }

          .job_ausgabe .job_bit__item {
              padding: 10px 20px;
          }

          .jobuser-entry__item {
              padding-left: 20px;
          }

          .jobuser__item.abteilung {
              font-weight: bold;
              padding: 10px 0 0;
          }

          .jobliste-user.adduser {
              padding-top: 5px;
          }

          .joblist__formitem label {
              font-weight: bold;
          }
    ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'jobliste.css')),
    'lastmodified' => time()
  );

  return $css;
}

/**
 * Stylesheet der eventuell hinzugefügt werden muss
 */
function jobliste_stylesheet_update()
{
  // Update-Stylesheet
  // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
  //arrays initialisieren
  $update_array_all = array();

  // $update_array_all[] = array(
  //   'stylesheet' => "
  //     /* update-userfilter - kommentar nicht entfernen */
  //       .scenefilteroptions__items.button {
  //           text-align: center;
  //           width: 100%;
  //       }
  //   ",
  //   'update_string' => 'update-userfilter'
  // );

  return $update_array_all;
}

/**
 * Update Check
 * @return boolean false wenn Plugin nicht aktuell ist
 * überprüft ob das Plugin auf der aktuellen Version ist
 */
function jobliste_is_updated()
{
  global $db;

  //testen ob alle einstellungen vorhanden sind
  $setting_array = jobliste_setting_array();
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'jobliste%' LIMIT 1;"), "gid");

    //alte einstellung aus der db holen
    $check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
    $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
    $check = $db->num_rows($check);

    if ($check == 0) {
      echo "Setting: {$name} muss hinzugefügt werden.";
      return false;
    } else {

      //die einstellung gibt es schon, wir testen ob etwas verändert wurde
      while ($setting_old = $db->fetch_array($check2)) {
        if (
          $setting_old['title'] != $setting['title'] ||
          $setting_old['description'] != $setting['description'] ||
          $setting_old['optionscode'] != $setting['optionscode'] ||
          $setting_old['disporder'] != $setting['disporder']
        ) {
          //wir wollen den value nicht überspeichern, also nur die anderen werte aktualisieren
          $update_array = array(
            'title' => $setting['title'],
            'description' => $setting['description'],
            'optionscode' => $setting['optionscode'],
            'disporder' => $setting['disporder']
          );

          echo "Setting: {$name} muss aktualisiert werde.<br>";
          return false;
        }
      }
    }
  }

  //Testen ob im CSS etwas fehlt
  $update_data_all = scenetracker_stylesheet_update();
  //alle Themes bekommen
  $theme_query = $db->simple_select('themes', 'tid, name');
  while ($theme = $db->fetch_array($theme_query)) {
    //wenn im style nicht vorhanden, dann gesamtes css hinzufügen
    $templatequery = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "themestylesheets` where tid = '{$theme['tid']}' and name ='scenetracker.css'");
    //scenetracker.css ist in keinem style nicht vorhanden
    if ($db->num_rows($templatequery) == 0) {
      echo ("Nicht im {$theme['tid']} vorhanden <br>");
      return false;
    } else {
      //scenetracker.css ist in einem style nicht vorhanden
      //css ist vorhanden, testen ob alle updatestrings vorhanden sind
      $update_data_all = scenetracker_stylesheet_update();
      //array durchgehen mit eventuell hinzuzufügenden strings
      foreach ($update_data_all as $update_data) {
        //String bei dem getestet wird ob er im alten css vorhanden ist
        $update_string = $update_data['update_string'];
        //updatestring darf nicht leer sein
        if (!empty($update_string)) {
          //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
          $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'scenetracker.css' AND stylesheet LIKE '%" . $update_string . "%' ");
          //string war nicht vorhanden
          if ($db->num_rows($test_ifin) == 0) {
            echo ("Mindestens Theme {$theme['tid']} muss aktualisiert werden <br>");
            return false;
          }
        }
      }
    }
  }

  //Testen ob eins der Templates aktualisiert werden muss
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = scenetracker_updated_templates();
  //alle themes durchgehen
  foreach ($update_template_all as $update_template) {
    //entsprechendes Tamplate holen
    $old_template_query = $db->simple_select("templates", "tid, template, sid", "title = '" . $update_template['templatename'] . "'");
    while ($old_template = $db->fetch_array($old_template_query)) {
      //pattern bilden
      if ($update_template['action'] == 'replace') {
        $pattern = scenetracker_createRegexPattern($update_template['change_string']);
        $check = preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'add') {
        //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
        $pattern = scenetracker_createRegexPattern($update_template['action_string']);
        $check = !preg_match($pattern, $old_template['template']);
      } elseif ($update_template['action'] == 'overwrite') {
        //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben
        $pattern = scenetracker_createRegexPattern($update_template['change_string']);
        $check = !preg_match($pattern, $old_template['template']);
      }
      //testen ob der zu ersetzende string vorhanden ist
      //wenn ja muss das template aktualisiert werden.
      if ($check) {
        $templateset = $db->fetch_field($db->simple_select("templatesets", "title", "sid = '{$old_template['sid']}'"), "title");
        echo ("Template {$update_template['templatename']} im Set {$templateset}'(SID: {$old_template['sid']}') muss aktualisiert werden.");
        return false;
      }
    }
  }

  return true;
}

// #####################################
// ### LARAS BIG MAGIC - RPG STUFF MODUL - THE FUNCTIONS ###
// #####################################

$plugins->add_hook("admin_rpgstuff_action_handler", "jobliste_admin_rpgstuff_action_handler");
function jobliste_admin_rpgstuff_action_handler(&$actions)
{
  $actions['jobliste_transfer'] = array('active' => 'jobliste_transfer', 'file' => 'jobliste_transfer');
  $actions['jobliste_updates'] = array('active' => 'jobliste_updates', 'file' => 'jobliste_updates');
}

// Benutzergruppen-Berechtigungen im ACP
$plugins->add_hook("admin_rpgstuff_permissions", "jobliste_admin_rpgstuff_permissions");
function jobliste_admin_rpgstuff_permissions(&$admin_permissions)
{
  global $lang;

  $admin_permissions['jobliste'] = "Darf Jobliste verwalten";

  return $admin_permissions;
}

// im Menü einfügen
// $plugins->add_hook("admin_rpgstuff_menu", "jobliste_admin_rpgstuff_menu");
// function jobliste_admin_rpgstuff_menu(&$sub_menu)
// {
//   global $lang;
//   $sub_menu[] = [
//     "id" => "jobliste",
//     "title" => "Jobliste Verwalten",
//     "link" => "index.php?module=rpgstuff-jobliste_transfer"
//   ];
// }

/**
 * Funktion um alte Templates des Plugins bei Bedarf zu aktualisieren
 */
function jobliste_replace_templates()
{
  global $db;
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = jobliste_updated_templates();
  if (!empty($update_template_all)) {
    //diese durchgehen
    foreach ($update_template_all as $update_template) {
      //anhand des templatenames holen
      $old_template_query = $db->simple_select("templates", "tid, template", "title = '" . $update_template['templatename'] . "'");
      //in old template speichern
      while ($old_template = $db->fetch_array($old_template_query)) {
        //was soll gefunden werden? das mit pattern ersetzen (wir schmeißen leertasten, tabs, etc raus)

        if ($update_template['action'] == 'replace') {
          $pattern = jobliste_createRegexPattern($update_template['change_string']);
        } elseif ($update_template['action'] == 'add') {
          //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
          $pattern = jobliste_createRegexPattern($update_template['action_string']);
        } elseif ($update_template['action'] == 'overwrite') {
          $pattern = jobliste_createRegexPattern($update_template['change_string']);
        }

        //was soll gemacht werden -> momentan nur replace 
        if ($update_template['action'] == 'replace') {
          //wir ersetzen wenn gefunden wird
          if (preg_match($pattern, $old_template['template'])) {
            $template = preg_replace($pattern, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -replace- {$update_template['templatename']} in {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'add') { //hinzufügen nicht ersetzen
          //ist es schon einmal hinzugefügt wurden? nur ausführen, wenn es noch nicht im template gefunden wird
          if (!preg_match($pattern, $old_template['template'])) {
            $pattern_rep = jobliste_createRegexPattern($update_template['change_string']);
            $template = preg_replace($pattern_rep, $update_template['action_string'], $old_template['template']);
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -add- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
        if ($update_template['action'] == 'overwrite') { //komplett ersetzen
          //checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben, wenn nicht überschreiben wir das ganze template
          if (!preg_match($pattern, $old_template['template'])) {
            $template = $update_template['action_string'];
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -overwrite- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
      }
    }
  }
}

/**
 * Hier werden Templates gespeichert, die im Laufe der Entwicklung aktualisiert wurden
 * @return array - template daten die geupdatet werden müssen
 * templatename: name des templates mit dem was passieren soll
 * change_string: nach welchem string soll im alten template gesucht werden
 * action: Was soll passieren - add: fügt hinzu, replace ersetzt (change)string, overwrite ersetzt gesamtes template
 * action_strin: Der string der eingefügt/mit dem ersetzt/mit dem überschrieben werden soll
 */
function jobliste_updated_templates()
{
  global $db;

  //data array initialisieren 
  $update_template = array();

  // $update_template[] = array(
  //   "templatename" => 'jobliste_index_reminder_bit',
  //   "change_string" => '({$lastpostdays} Tage)',
  //   "action" => 'add',
  //   "action_string" => '({$lastpostdays} Tage) - <a href="index.php?action=reminder&sceneid={$sceneid}">[ignore and hide]</a>'
  // );

  // $update_template[] = array(
  //   "templatename" => 'jobliste_index_reminder',
  //   "change_string" => '<a href="index.php?action=reminder">[ignore all]</a>',
  //   "action" => 'replace',
  //   "action_string" => '<a href="index.php?action=reminder_all">[anzeige deaktivieren]</a>'
  // );

  // $update_template[] = array(
  //   "templatename" => 'jobliste_popup',
  //   "change_string" => '{$jobliste_popup_select_options_index}',
  //   "action" => 'overwrite',
  //   "action_string" => ''
  // );

  return $update_template;
}

/**
 * Funktion um ein pattern für preg_replace zu erstellen
 * und so templates zu vergleichen.
 * @return string - pattern für preg_replace zum vergleich
 */
function jobliste_createRegexPattern($html)
{
  // Entkomme alle Sonderzeichen und ersetze Leerzeichen mit flexiblen Platzhaltern
  $pattern = preg_quote($html, '/');

  // Ersetze Leerzeichen in `class`-Attributen mit `\s+` (flexible Leerzeichen)
  $pattern = preg_replace('/\s+/', '\\s+', $pattern);

  // Passe das Muster an, um Anfang und Ende zu markieren
  return '/' . $pattern . '/si';
}

$plugins->add_hook('admin_rpgstuff_update_plugin', "jobliste_admin_update_plugin");
// jobliste_admin_update_plugin
function jobliste_admin_update_plugin(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_plugin_updates');

  // UPDATE KRAM
  // Update durchführen
  if ($mybb->input['action'] == 'add_update' and $mybb->get_input('plugin') == "jobliste") {

    //Settings updaten
    jobliste_add_settings("update");
    rebuild_settings();

    //templates hinzufügen
    jobliste_add_templates("update");

    //templates bearbeiten wenn nötig
    jobliste_replace_templates();

    //Datenbank updaten
    jobliste_database("update");

    //Stylesheet hinzufügen wenn nötig:
    //array mit updates bekommen.
    $update_data_all = jobliste_stylesheet_update();
    //alle Themes bekommen
    $theme_query = $db->simple_select('themes', 'tid, name');
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    while ($theme = $db->fetch_array($theme_query)) {
      //wenn im style nicht vorhanden, dann gesamtes css hinzufügen
      $templatequery = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "themestylesheets` where tid = '{$theme['tid']}' and name ='jobliste.css'");

      if ($db->num_rows($templatequery) == 0) {
        $css = jobliste_stylesheet($theme['tid']);

        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "jobliste.css"), "sid = '" . $sid . "'", 1);
        update_theme_stylesheet_list($theme['tid']);
      }

      //testen ob updatestring vorhanden - sonst an css in theme hinzufügen
      $update_data_all = jobliste_stylesheet_update();
      //array durchgehen mit eventuell hinzuzufügenden strings
      foreach ($update_data_all as $update_data) {
        //hinzuzufügegendes css
        $update_stylesheet = $update_data['stylesheet'];
        //String bei dem getestet wird ob er im alten css vorhanden ist
        $update_string = $update_data['update_string'];
        //updatestring darf nicht leer sein
        if (!empty($update_string)) {
          //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
          $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'jobliste.css' AND stylesheet LIKE '%" . $update_string . "%' ");
          //string war nicht vorhanden
          if ($db->num_rows($test_ifin) == 0) {
            //altes css holen
            $oldstylesheet = $db->fetch_field($db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'jobliste.css'"), "stylesheet");
            //Hier basteln wir unser neues array zum update und hängen das neue css hinten an das alte dran
            $updated_stylesheet = array(
              "cachefile" => $db->escape_string('jobliste.css'),
              "stylesheet" => $db->escape_string($oldstylesheet . "\n\n" . $update_stylesheet),
              "lastmodified" => TIME_NOW
            );
            $db->update_query("themestylesheets", $updated_stylesheet, "name='jobliste.css' AND tid = '{$theme['tid']}'");
            echo "In Theme mit der ID {$theme['tid']} wurde CSS hinzugefügt -  $update_string <br>";
          }
        }
        update_theme_stylesheet_list($theme['tid']);
      }
    }
  }

  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Jobliste") . "</b>", array('width' => '70%'));

  // Überprüfen, ob Update nötig ist 
  $update_check = jobliste_is_updated();

  if ($update_check) {
    $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=jobliste\">" . $lang->plugins_update . "</a>", array('class' => 'align_center'));
  }

  $table->construct_row();
}


/**
 * Funktion um CSS nachträglich oder nach einem MyBB Update wieder hinzuzufügen
 */
$plugins->add_hook('admin_rpgstuff_update_stylesheet', "jobliste_admin_update_stylesheet");
function jobliste_admin_update_stylesheet(&$table)
{
  global $db, $mybb, $lang;

  $lang->load('rpgstuff_stylesheet_updates');

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  // HINZUFÜGEN
  if ($mybb->input['action'] == 'add_master' and $mybb->get_input('plugin') == "jobliste") {

    $css = jobliste_stylesheet();

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "jobliste.css"), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
      update_theme_stylesheet_list($theme['tid']);
    }

    flash_message($lang->stylesheets_flash, "success");
    admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
  }

  // Zelle mit dem Namen des Themes
  $table->construct_cell("<b>" . htmlspecialchars_uni("Jobliste-Manager") . "</b>", array('width' => '70%'));

  // Ob im Master Style vorhanden
  $master_check = $db->query("SELECT tid FROM " . TABLE_PREFIX . "themestylesheets 
    WHERE name = 'jobliste.css' 
    AND tid = 1");

  if ($db->num_rows($master_check) > 0) {
    $masterstyle = true;
  } else {
    $masterstyle = false;
  }

  if (!empty($masterstyle)) {
    $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
  } else {
    $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=jobliste\">" . $lang->stylesheets_add . "</a>", array('class' => 'align_center'));
  }
  $table->construct_row();
}
