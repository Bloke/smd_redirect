<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_redirect';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.12';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Redirect URLs from one place to another';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '1';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<<EOT
#@smd_redir
smd_redir_added => Redirect added
smd_redir_btn_new => New redirect
smd_redir_btn_pref => Prefs
smd_redir_control_panel => Control panel
smd_redir_deleting =>  Deleting...
smd_redir_destination => Destination
smd_redir_err_need_source => You must supply a source URL
smd_redir_saving =>  Saving...
smd_redir_search => Search
smd_redir_source => Source
smd_redir_tab_name => Redirects
smd_redir_updating =>  Updating...
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_redirect
 *
 * A Textpattern CMS plugin for redirecting URLs from place to place
 *
 * @author Stef Dawson
 * @link   http://stefdawson.com/
 */
if (txpinterface === 'admin') {
    global $smd_redir_event;
    $smd_redir_event = 'smd_redir';

    add_privs($smd_redir_event, '1');
    register_tab('extensions', $smd_redir_event, gTxt('smd_redir_tab_name'));
    register_callback('smd_redir_dispatcher', $smd_redir_event);
    register_callback('smd_redir_css', 'admin_side', 'head_end');
} elseif (txpinterface === 'public') {
    register_callback('smd_redirect', 'pretext_end');
}

/**
 * Jump off point for event/steps.
 *
 * @param string $evt Textpattern event
 * @param string $stp Textpattern step (action)
 */
function smd_redir_dispatcher($evt, $stp)
{
    global $smd_redir_event;

    $available_steps = array(
        'smd_redir'        => false,
        'smd_redir_create' => true,
        'smd_redir_save'   => true,
        'smd_redir_prefs'  => false,
        'save_pane_state'  => true,
    );

    if ($stp == 'save_pane_state') {
        smd_redir_save_pane_state();
    } elseif (!$stp or !bouncer($stp, $available_steps)) {
        $stp = $smd_redir_event;
    }

    $stp();
}

/**
 * Render the plugin's CSS.
 *
 * @param string $evt Textpattern event
 * @param string $stp Textpattern step (action)
 */
function smd_redir_css($evt = '', $stp = '')
{
    global $event, $smd_redir_event;

    if ($event === $smd_redir_event) {
        $smd_redir_styles = array(
            'list' =>
             '.smd_hidden { display:none; }
              #smd_redir_container { text-align:center; }
              #smd_redirects { padding:0; margin:0 auto; width:70%; list-style-type:none; }
              #smd_redirects li { padding:5px; border:solid 1px black; background-color:#e2dfce; text-align:center; color:#80551e}
              #smd_redirects li .smd_redir_src { cursor: pointer; }
              #smd_redirects li.edited { font-size:140%; }
              .smd_redir_item label { width:6.2em; display:inline-block; }
              .smd_redir_item input { width:70%; }
              #smd_redir_btnpanel li { list-style-type:none; }
              .smd_redir_grab { float:right; font-size:115%; }
              .placeHolder div { background-color:white !important; border:dashed 1px gray !important; }
              #smd_redir_cpanel form { margin:10px; }
              #smd_redir_cpanel form label { margin:0 6px; }
              .fieldset_inner { background-image:none; background-color:transparent; border:0; }
              #smd_redir_cpanel select, #smd_redir_cpanel input[type="text"] { margin-bottom:10px; }
              #smd_redir_cpanel input[type="text"] { padding:3px; }
              #smd_redir_control_panel { margin:0 auto 20px; width:600px; }
                 .btnpref { float:right; }',
        );

        echo '<style type="text/css">' . $smd_redir_styles['list'] . '</style>';
    }
}

/**
 * Main admin interface.
 *
 * @param string $msg Status message to display
 */
function smd_redir($msg = '')
{
    global $smd_redir_event, $smd_redir_styles;

    pagetop(gTxt('smd_redir_tab_name'), $msg);

    // Grab the latest redirect points
    $redirects = smd_redir_get(1);

    // Set up the buttons and column info
    $newbtn = '<a class="navlink" href="#" onclick="return smd_redir_togglenew();">' . gTxt('smd_redir_btn_new').'</a>';
    $prefbtn = '<a class="navlink btnpref" href="?event=' . $smd_redir_event . a . 'step=smd_redir_prefs">' . gTxt('smd_redir_btn_pref').'</a>';
    $status = '<span id="smd_redir_status"></span>';

    $qs = array(
        "event" => $smd_redir_event,
    );

    $qsVars = "index.php" . join_qs($qs);

    // i18n values for javascript
    $red_src = gTxt('smd_redir_source');
    $red_dst = gTxt('smd_redir_destination');
    $red_del = gTxt('smd_redir_deleting');
    $red_sav = gTxt('smd_redir_saving');
    $red_upd = gTxt('smd_redir_updating');
    $red_btn_del = gTxt('delete');
    $red_btn_sav = gTxt('save');

        echo script_js(<<<EOC
function smd_redir_togglenew() {
    // Revert any currently edited item first
    smd_redir_unedit();
    box = jQuery("#smd_redir_create");
    if (box.css("display") == "none") {
        box.show();
    } else {
        box.hide();
    }
    jQuery("input.smd_focus").focus();
    return false;
}

// Remove edit styling.
function smd_redir_unedit() {
    jQuery('#smd_redirects li.edited').each(function() {
        jQuery(this).removeClass('edited');
        ke = jQuery(this).find('input[name="smd_redir_src_orig"]').val();
        vl = jQuery(this).find('input[name="smd_redir_dest_orig"]').val();

        jQuery(this).find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
    });
}

// Remove edit styling and save the current redirect items.
function smd_redir_save(pos) {
    jQuery('#smd_redir_status').text('{$red_sav}');

    // Would love to pass the object in directly, meh, can't figure it out.
    obj = jQuery('#smd_redirects li[data-itemidx='+pos+']');

    // Revert the input controls to regular text items
    ke = obj.find('input[name=smd_redir_src]').val();
    vl = obj.find('input[name=smd_redir_dest]').val();
    obj.find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
    obj.removeClass('edited');

    smd_redir_post();
}

// Remove the given item and save the remaining redirect items.
function smd_redir_delete(pos) {
    jQuery('#smd_redir_status').text('{$red_del}');

    // Would love to pass the object in directly, meh, can't figure it out.
    obj = jQuery('#smd_redirects li[data-itemidx="'+pos+'"]');
    obj.remove();

    smd_redir_post();
}

// Save the redirect list to the prefs.
function smd_redir_post() {
    // Loop over the entire redirects collection, extract the original source, the new source and the destination,
    // then stuff them in a dedicated DOM element...
    var data = [];
    jQuery('#smd_redirects li').each(function(idx, obj) {
        var me = jQuery(obj);
        var orig = me.find('[name=smd_redir_src_orig]').val();
        var from = me.find('.smd_redir_src').text();
        var dest = me.find('.smd_redir_dest').text();

        data.push({ orig: orig, from: from, dest: dest });
    });

    // ... and send the entire lot off to be stored
    // TODO: handle timeout/failure etc
    jQuery.post('{$qsVars}', {
            step: "smd_redir_save",
            smd_redir_data: JSON.stringify(data),
            _txp_token : textpattern._txp_token
        },
        function(data) {

            jQuery('#smd_redir_status').text('');

            // Retrigger the search in case the results have changed after edit.
            jQuery('#smd_redir_search').keyup();
        }
    );
}

function smd_redir_filter(selector, query, nam, csense, exact) {
    var query = jQuery.trim(query);
    csense = (csense) ? "" : "i";
    query = query.replace(/ /gi, '|'); // add OR for regex query
    if (exact) {
        tmp = query.split('|');
        for (var idx = 0; idx < tmp.length; idx++) {
            tmp[idx] = '^'+tmp[idx]+'$';
        }
        query = tmp.join('|');
    }
    var re = new RegExp(query, csense);
    jQuery(selector).each(function() {
        sel = (typeof nam=="undefined" || nam=='') ? jQuery(this) : jQuery(this).find("."+nam+"");
        if (query == '') {
            if (sel.length == 1 && sel.text() == '') {
                jQuery(this).show();
            } else {
                jQuery(this).hide();
            }
        } else {
            if (sel.text().search(re) < 0) {
                jQuery(this).hide();
            } else {
                jQuery(this).show();
            }
        }
    });
}

jQuery(function() {
    jQuery("#smd_redirects").dragsort({
        dragSelector: ".smd_redir_grab",
        dragEnd: function() {
            jQuery('#smd_redir_status').text('{$red_upd}');
            smd_redir_unedit(); // Remove any current edit status
            smd_redir_post(); // Update the list on the server with the new order
        },
        dragBetween: false,
        placeHolderTemplate: "<li class='placeHolder'><div></div></li>"
    });

    jQuery(".smd_redir_src.closed").on("click", function() {
        var me = jQuery(this);
        me.toggleClass('closed');
        smd_redir_unedit();

        key = me.text();
        val = me.next().text();

        me.html('<label for="smd_redir_src">{$red_src}</label><input type="text" id="smd_redir_src" name="smd_redir_src" value="'+key+'" />');
        me.next().html('<label for="smd_redir_dest">{$red_dst}</label><input type="text" name="smd_redir_dest" value="'+val+'" />')
            .append('<div><button type="button" id="smd_redir_save" name="smd_redir_save" onclick="smd_redir_save(' + me.parent().parent().attr('data-itemidx') + ');">{$red_btn_sav}</button><button type="button" id="smd_redir_delete" name="smd_redir_delete" onclick="smd_redir_delete(' + me.parent().parent().attr('data-itemidx') + ');">{$red_btn_del}</button></div>');
        me.parent().parent().addClass('edited');
        me.find('input[name="smd_redir_src"]').focus();
    });

    jQuery("#smd_redir_search").keyup(function(event) {
        // if esc is pressed or nothing is entered
        if (event.keyCode == 27 || jQuery(this).val() == '') {
            jQuery(this).val('');
            jQuery("#smd_redirects li").show();
        } else {
            smd_redir_filter('#smd_redirects li', jQuery(this).val(), jQuery("#smd_redir_filt").val(), 0, 0);
        }
    });

    jQuery("#smd_redir_filt").change(function(event) {
        if (jQuery('#smd_redir_search').val() == '') {
            jQuery("#smd_redirects li").show();
        } else {
            smd_redir_filter('#smd_redirects li', jQuery("#smd_redir_search").val(), jQuery(this).val(), 0, 0);
        }
    });
});
EOC
    );

    // Inject Drag n drop jQuery interface
    echo smd_redir_dragdrop();

    $ftypes = array(
        'smd_redir_src'  => gTxt('smd_redir_source'),
        'smd_redir_dest' => gTxt('smd_redir_destination'),
    );

    // Control panel
    echo '<div id="smd_container">';
    echo '<fieldset id="smd_redir_control_panel" class="txp-control-panel"><legend class="plain lever' . (get_pref('pane_smd_redir_cpanel_visible') ? ' expanded' : '').'"><a href="#smd_redir_cpanel">' . gTxt('smd_redir_control_panel').'</a></legend>';
    echo '<div id="smd_redir_cpanel" class="toggle" style="display:' . (get_pref('pane_smd_redir_cpanel_visible') ? 'block' : 'none').'">';

    echo '<form id="smd_redir_filtform" action="index.php" method="post">';
    echo '<label for="smd_redir_search">' . gTxt('smd_redir_search') . '</label>'
        . '<span id="smd_redir_searchby">'
            .selectInput('smd_redir_filt', $ftypes, '', 0, '', 'smd_redir_filt')
        . '</span>'
        . fInput('text', 'smd_redir_search', '', '', '', '', '', '', 'smd_redir_search')
        . $prefbtn;
    echo eInput($smd_redir_event) . sInput('smd_redir_filter');
    echo '</form>';

    echo '</div>';
    echo '</fieldset>';

    // Redirect list
    echo n . '<div id="' . $smd_redir_event . '_container" class="txp-container txp-list">';
    echo '<form name="smd_redir_form" id="smd_redir_form" action="index.php" method="post">';
    echo '<ul id="smd_redir_btnpanel">';
    echo n . '<li id="smd_redir_buttons">' . $newbtn . sp . $status . '</li>';
    echo '<li id="smd_redir_create" class="smd_hidden">'
            . '<label for="smd_redir_newsource">' . gTxt('smd_redir_source') . '</label>' . fInput('text', 'smd_redir_newsource', '', 'smd_focus', '', '', '70', '' ,'smd_redir_newsource')
            . br . '<label for="smd_redir_destination">' . gTxt('smd_redir_destination') . '</label>' . fInput('text', 'smd_redir_destination', '', '', '', '', '70', '' ,'smd_redir_destination')
            . fInput('submit', 'smd_redir_add', gTxt('add'), 'smallerbox', '', '', '', '', 'smd_redir_add')
            . eInput($smd_redir_event)
            . sInput('smd_redir_create')
            . tInput();
    echo '</li></ul>';
    echo '</form>';

    // Remaining redirects
    echo '<ul id="smd_redirects">';

    foreach ($redirects as $idx => $items) {
        echo '<li>
            <span class="smd_redir_grab">&#8657;<br />&#8659;</span>
            <input type="hidden" name="smd_redir_src_orig" value="' . $items['src'] . '" />
            <input type="hidden" name="smd_redir_dest_orig" value="' . $items['dst'] . '" />
            <div class="smd_redir_item">
                <div class="smd_redir_src closed">' . $items['src'] . '</div>
                <div class="smd_redir_dest">' . $items['dst'] . '</div>
            </div>
        </li>';
    }

    echo '</ul>';
    echo '</div>';
}

/**
 * Create a redirect from the admin side's 'New' button.
 */
function smd_redir_create()
{
    extract(gpsa(array('smd_redir_newsource', 'smd_redir_destination')));

    $out = array();

    if ($smd_redir_newsource) {
        $redirects = smd_redir_get(0);

        $found = 0;

        foreach ($redirects as $idx => $items) {
            if ($items['src'] != $smd_redir_newsource) {
                // Let existing rules through
                $out[] = array('src' => $items['src'], 'dst' => $items['dst']);
            } else {
                // Update to an existing rule
                $out[] = array('src' => $items['src'], 'dst' => $smd_redir_destination);
                $found++;
            }
        }

        // Redirect doesn't already exist so add it.
        if ($found === 0) {
            $out[] = array('src' => $smd_redir_newsource, 'dst' => $smd_redir_destination);
        }

        set_pref('smd_redirects', smd_redir_serialize($out), 'smd_redir', PREF_HIDDEN, '', 0);

        $msg = gTxt('smd_redir_added');
    } else {
        $msg = array(gTxt('smd_redir_err_need_source'), E_ERROR);
    }

    smd_redir($msg);
}

/**
 * Save the given list of redirects to the prefs array.
 */
function smd_redir_save()
{
    $data = json_decode(ps('smd_redir_data'), true);
    $out = array();

    foreach($data as $items) {
        $out[] = array('src' => $items['from'], 'dst' => $items['dest']);
    }

    set_pref('smd_redirects', smd_redir_serialize($out), 'smd_redir', PREF_HIDDEN, '', 0);
    send_xml_response();
    exit;
}

/**
 * Fetch the list of redirects currently in force.
 *
 * @param int $force Whether to force fetching the prefs from the DB to prevent stale values
 */
function smd_redir_get($force = 0)
{
    $redirects = get_pref('smd_redirects', array(), $force);
    $redirects = ($redirects) ? smd_redir_unserialize($redirects) : array();

    return $redirects;
}

/**
 * Safe-ify saved pref values, since we're dealing with preg_match patterns.
 */
function smd_redir_serialize($obj)
{
    $crush = smd_redir_check_crush();
    return chunk_split(base64_encode((($crush) ? gzcompress(serialize($obj)) : serialize($obj))));
}

/**
 * Retrieve (unescape) saved pref values for display purposes.
 */
function smd_redir_unserialize($txt)
{
    $crush = smd_redir_check_crush();
    return unserialize((($crush) ? gzuncompress(base64_decode($txt)) : base64_decode($txt)));
}

/**
 * Check if the ability to compress (gzip) content is available.
 *
 * @return bool
 */
function smd_redir_check_crush()
{
    return (function_exists('gzcompress') && function_exists('gzuncompress'));
}

/**
 * Prefs panel.
 *
 * @todo
 */
function smd_redir_prefs($msg='')
{
    pagetop(gTxt('smd_redir_tab_name'), $msg);
    echo '<p>Coming soon</p>';
}

/**
 * Change and store qty-per-page value.
 *
 * @todo Needed any more?
 */
function smd_redir_change_pageby()
{
    event_change_pageby('smd_redir');
    smd_redir();
}

/**
 * The search dropdown list.
 *
 * @param string $crit   Search criteria
 * @param string $method Search method (field) to search against
 */
function smd_redir_search_form($crit, $method)
{
    global $smd_redir_event;

    $methods = array(
        'source'      => gTxt('smd_redir_source'),
        'destination' => gTxt('smd_redir_destination'),
    );

    return search_form($smd_redir_event, '', $crit, $methods, $method, 'source');
}

/**
 * Save the state of the twisties.
 *
 * @todo Not needed from 4.6+
 */
function smd_redir_save_pane_state()
{
    $panes = array('smd_redir_cpanel');
    $pane = gps('pane');

    if (in_array($pane, $panes)) {
        set_pref("pane_{$pane}_visible", (gps('visible') == 'true' ? '1' : '0'), 'smd_redir', PREF_HIDDEN, 'yesnoradio', 0, PREF_PRIVATE);
        send_xml_response();
    } else {
        send_xml_response(array('http-status' => '400 Bad Request'));
    }
}

/**
 * A base64-encoded version of DragSort (http://dragsort.codeplex.com/).
 */
function smd_redir_dragdrop()
{
    return script_js(base64_decode('
Ly8galF1ZXJ5IExpc3QgRHJhZ1NvcnQgdjAuNg0KLy8gV2Vic2l0ZTogaHR0cDovL2dpdGh1Yi5j
b20vaG9uZXN0YmxlZXBzL2pxdWVyeS1kcmFnc29ydA0KDQovLyBUaGlzIGNvZGUgaXMgYWRhcHRl
ZCBmcm9tIGpRdWVyeSBkcmFnc29ydCBvcmlnaW5hbGx5IGF0DQovLyBodHRwOi8vZHJhZ3NvcnQu
Y29kZXBsZXguY29tLyBidXQgbm8gbG9uZ2VyIG1haW50YW5lZC4NCi8vIEl0IHByb3ZpZGVzIGEg
bWVhbnMgb2YgZHJhZy9zb3J0IHdpdGhvdXQgdGhlIG5lZWQgZm9yIGENCi8vIGxhcmdlIGpRdWVy
eSBVSSBsaWJyYXJ5IGluIGNhc2UgdGhhdCdzIGFsbCB5b3UgbmVlZCwgYW5kDQovLyBoYXMgYmVl
biB1cGRhdGVkIHRvIHN1cHBvcnQgalF1ZXJ5IDEuOSsuDQoNCihmdW5jdGlvbigkKSB7DQoNCgkk
LmZuLmRyYWdzb3J0ID0gZnVuY3Rpb24ob3B0aW9ucykgew0KCQlpZiAob3B0aW9ucyA9PSAiZGVz
dHJveSIpIHsNCgkJCSQodGhpcy5zZWxlY3RvcikudHJpZ2dlcigiZHJhZ3NvcnQtdW5pbml0Iik7
DQoJCQlyZXR1cm47DQoJCX0NCgkJdmFyIG9wdHMgPSAkLmV4dGVuZCh7fSwgJC5mbi5kcmFnc29y
dC5kZWZhdWx0cywgb3B0aW9ucyk7DQoJCXZhciBsaXN0cyA9IFtdOw0KCQl2YXIgbGlzdCA9IG51
bGwsIGxhc3RQb3MgPSBudWxsOw0KDQoJCXRoaXMuZWFjaChmdW5jdGlvbihpLCBjb250KSB7DQoN
CgkJCS8vaWYgbGlzdCBjb250YWluZXIgaXMgdGFibGUsIHRoZSBicm93c2VyIGF1dG9tYXRpY2Fs
bHkgd3JhcHMgcm93cyBpbiB0Ym9keSBpZiBub3Qgc3BlY2lmaWVkIHNvIGNoYW5nZSBsaXN0IGNv
bnRhaW5lciB0byB0Ym9keSBzbyB0aGF0IGNoaWxkcmVuIHJldHVybnMgcm93cyBhcyB1c2VyIGV4
cGVjdGVkDQoJCQlpZiAoJChjb250KS5pcygidGFibGUiKSAmJiAkKGNvbnQpLmNoaWxkcmVuKCku
bGVuZ3RoID09IDEgJiYgJChjb250KS5jaGlsZHJlbigpLmlzKCJ0Ym9keSIpKQ0KCQkJCWNvbnQg
PSAkKGNvbnQpLmNoaWxkcmVuKCkuZ2V0KDApOw0KDQoJCQl2YXIgbmV3TGlzdCA9IHsNCgkJCQlk
cmFnZ2VkSXRlbTogbnVsbCwNCgkJCQlwbGFjZUhvbGRlckl0ZW06IG51bGwsDQoJCQkJcG9zOiBu
dWxsLA0KCQkJCW9mZnNldDogbnVsbCwNCgkJCQlvZmZzZXRMaW1pdDogbnVsbCwNCgkJCQlzY3Jv
bGw6IG51bGwsDQoJCQkJY29udGFpbmVyOiBjb250LA0KDQoJCQkJaW5pdDogZnVuY3Rpb24oKSB7
DQoJCQkJCS8vc2V0IG9wdGlvbnMgdG8gZGVmYXVsdCB2YWx1ZXMgaWYgbm90IHNldA0KCQkJCQlv
cHRzLnRhZ05hbWUgPSAkKHRoaXMuY29udGFpbmVyKS5jaGlsZHJlbigpLmxlbmd0aCA9PSAwID8g
ImxpIiA6ICQodGhpcy5jb250YWluZXIpLmNoaWxkcmVuKCkuZ2V0KDApLnRhZ05hbWUudG9Mb3dl
ckNhc2UoKTsNCgkJCQkJaWYgKG9wdHMuaXRlbVNlbGVjdG9yID09ICIiKQ0KCQkJCQkJb3B0cy5p
dGVtU2VsZWN0b3IgPSBvcHRzLnRhZ05hbWU7DQoJCQkJCWlmIChvcHRzLmRyYWdTZWxlY3RvciA9
PSAiIikNCgkJCQkJCW9wdHMuZHJhZ1NlbGVjdG9yID0gb3B0cy50YWdOYW1lOw0KCQkJCQlpZiAo
b3B0cy5wbGFjZUhvbGRlclRlbXBsYXRlID09ICIiKQ0KCQkJCQkJb3B0cy5wbGFjZUhvbGRlclRl
bXBsYXRlID0gIjwiICsgb3B0cy50YWdOYW1lICsgIj4mbmJzcDs8LyIgKyBvcHRzLnRhZ05hbWUg
KyAiPiI7DQoNCgkJCQkJLy9saXN0aWR4IGFsbG93cyByZWZlcmVuY2UgYmFjayB0byBjb3JyZWN0
IGxpc3QgdmFyaWFibGUgaW5zdGFuY2UNCgkJCQkJJCh0aGlzLmNvbnRhaW5lcikuYXR0cigiZGF0
YS1saXN0aWR4IiwgaSkubW91c2Vkb3duKHRoaXMuZ3JhYkl0ZW0pLmJpbmQoImRyYWdzb3J0LXVu
aW5pdCIsIHRoaXMudW5pbml0KTsNCgkJCQkJdGhpcy5zdHlsZURyYWdIYW5kbGVycyh0cnVlKTsN
CgkJCQl9LA0KDQoJCQkJdW5pbml0OiBmdW5jdGlvbigpIHsNCgkJCQkJdmFyIGxpc3QgPSBsaXN0
c1skKHRoaXMpLmF0dHIoImRhdGEtbGlzdGlkeCIpXTsNCgkJCQkJJChsaXN0LmNvbnRhaW5lciku
dW5iaW5kKCJtb3VzZWRvd24iLCBsaXN0LmdyYWJJdGVtKS51bmJpbmQoImRyYWdzb3J0LXVuaW5p
dCIpOw0KCQkJCQlsaXN0LnN0eWxlRHJhZ0hhbmRsZXJzKGZhbHNlKTsNCgkJCQl9LA0KDQoJCQkJ
Z2V0SXRlbXM6IGZ1bmN0aW9uKCkgew0KCQkJCQlyZXR1cm4gJCh0aGlzLmNvbnRhaW5lcikuY2hp
bGRyZW4ob3B0cy5pdGVtU2VsZWN0b3IpOw0KCQkJCX0sDQoNCgkJCQlzdHlsZURyYWdIYW5kbGVy
czogZnVuY3Rpb24oY3Vyc29yKSB7DQoJCQkJCXRoaXMuZ2V0SXRlbXMoKS5tYXAoZnVuY3Rpb24o
KSB7IHJldHVybiAkKHRoaXMpLmlzKG9wdHMuZHJhZ1NlbGVjdG9yKSA/IHRoaXMgOiAkKHRoaXMp
LmZpbmQob3B0cy5kcmFnU2VsZWN0b3IpLmdldCgpOyB9KS5jc3MoImN1cnNvciIsIGN1cnNvciA/
ICJwb2ludGVyIiA6ICIiKTsNCgkJCQl9LA0KDQoJCQkJZ3JhYkl0ZW06IGZ1bmN0aW9uKGUpIHsN
CgkJCQkJdmFyIGxpc3QgPSBsaXN0c1skKHRoaXMpLmF0dHIoImRhdGEtbGlzdGlkeCIpXTsNCgkJ
CQkJdmFyIGl0ZW0gPSAkKGUudGFyZ2V0KS5jbG9zZXN0KCJbZGF0YS1saXN0aWR4XSA+ICIgKyBv
cHRzLnRhZ05hbWUpLmdldCgwKTsNCgkJCQkJdmFyIGluc2lkZU1vdmVhYmxlSXRlbSA9IGxpc3Qu
Z2V0SXRlbXMoKS5maWx0ZXIoZnVuY3Rpb24oKSB7IHJldHVybiB0aGlzID09IGl0ZW07IH0pLmxl
bmd0aCA+IDA7DQoNCgkJCQkJLy9pZiBub3QgbGVmdCBjbGljayBvciBpZiBjbGlja2VkIG9uIGV4
Y2x1ZGVkIGVsZW1lbnQgKGUuZy4gdGV4dCBib3gpIG9yIG5vdCBhIG1vdmVhYmxlIGxpc3QgaXRl
bSByZXR1cm4NCgkJCQkJaWYgKGUud2hpY2ggIT0gMSB8fCAkKGUudGFyZ2V0KS5pcyhvcHRzLmRy
YWdTZWxlY3RvckV4Y2x1ZGUpIHx8ICQoZS50YXJnZXQpLmNsb3Nlc3Qob3B0cy5kcmFnU2VsZWN0
b3JFeGNsdWRlKS5sZW5ndGggPiAwIHx8ICFpbnNpZGVNb3ZlYWJsZUl0ZW0pDQoJCQkJCQlyZXR1
cm47DQoNCgkJCQkJLy9wcmV2ZW50cyBzZWxlY3Rpb24sIHN0b3BzIGlzc3VlIG9uIEZ4IHdoZXJl
IGRyYWdnaW5nIGh5cGVybGluayBkb2Vzbid0IHdvcmsgYW5kIG9uIElFIHdoZXJlIGl0IHRyaWdn
ZXJzIG1vdXNlbW92ZSBldmVuIHRob3VnaCBtb3VzZSBoYXNuJ3QgbW92ZWQsDQoJCQkJCS8vZG9l
cyBhbHNvIHN0b3AgYmVpbmcgYWJsZSB0byBjbGljayB0ZXh0IGJveGVzIGhlbmNlIGRyYWdnaW5n
IG9uIHRleHQgYm94ZXMgYnkgZGVmYXVsdCBpcyBkaXNhYmxlZCBpbiBkcmFnU2VsZWN0b3JFeGNs
dWRlDQoJCQkJCWUucHJldmVudERlZmF1bHQoKTsNCg0KCQkJCQkvL2NoYW5nZSBjdXJzb3IgdG8g
bW92ZSB3aGlsZSBkcmFnZ2luZw0KCQkJCQl2YXIgZHJhZ0hhbmRsZSA9IGUudGFyZ2V0Ow0KCQkJ
CQl3aGlsZSAoISQoZHJhZ0hhbmRsZSkuaXMob3B0cy5kcmFnU2VsZWN0b3IpKSB7DQoJCQkJCQlp
ZiAoZHJhZ0hhbmRsZSA9PSB0aGlzKSByZXR1cm47DQoJCQkJCQlkcmFnSGFuZGxlID0gZHJhZ0hh
bmRsZS5wYXJlbnROb2RlOw0KCQkJCQl9DQoJCQkJCSQoZHJhZ0hhbmRsZSkuYXR0cigiZGF0YS1j
dXJzb3IiLCAkKGRyYWdIYW5kbGUpLmNzcygiY3Vyc29yIikpOw0KCQkJCQkkKGRyYWdIYW5kbGUp
LmNzcygiY3Vyc29yIiwgIm1vdmUiKTsNCg0KCQkJCQkvL29uIG1vdXNlZG93biB3YWl0IGZvciBt
b3ZlbWVudCBvZiBtb3VzZSBiZWZvcmUgdHJpZ2dlcmluZyBkcmFnc29ydCBzY3JpcHQgKGRyYWdT
dGFydCkgdG8gYWxsb3cgY2xpY2tpbmcgb2YgaHlwZXJsaW5rcyB0byB3b3JrDQoJCQkJCXZhciBs
aXN0RWxlbSA9IHRoaXM7DQoJCQkJCXZhciB0cmlnZ2VyID0gZnVuY3Rpb24oKSB7DQoJCQkJCQls
aXN0LmRyYWdTdGFydC5jYWxsKGxpc3RFbGVtLCBlKTsNCgkJCQkJCSQobGlzdC5jb250YWluZXIp
LnVuYmluZCgibW91c2Vtb3ZlIiwgdHJpZ2dlcik7DQoJCQkJCX07DQoJCQkJCSQobGlzdC5jb250
YWluZXIpLm1vdXNlbW92ZSh0cmlnZ2VyKS5tb3VzZXVwKGZ1bmN0aW9uKCkgeyAkKGxpc3QuY29u
dGFpbmVyKS51bmJpbmQoIm1vdXNlbW92ZSIsIHRyaWdnZXIpOyAkKGRyYWdIYW5kbGUpLmNzcygi
Y3Vyc29yIiwgJChkcmFnSGFuZGxlKS5hdHRyKCJkYXRhLWN1cnNvciIpKTsgfSk7DQoJCQkJfSwN
Cg0KCQkJCWRyYWdTdGFydDogZnVuY3Rpb24oZSkgew0KCQkJCQlpZiAobGlzdCAhPSBudWxsICYm
IGxpc3QuZHJhZ2dlZEl0ZW0gIT0gbnVsbCkNCgkJCQkJCWxpc3QuZHJvcEl0ZW0oKTsNCg0KCQkJ
CQlsaXN0ID0gbGlzdHNbJCh0aGlzKS5hdHRyKCJkYXRhLWxpc3RpZHgiKV07DQoJCQkJCWxpc3Qu
ZHJhZ2dlZEl0ZW0gPSAkKGUudGFyZ2V0KS5jbG9zZXN0KCJbZGF0YS1saXN0aWR4XSA+ICIgKyBv
cHRzLnRhZ05hbWUpDQoNCgkJCQkJLy9yZWNvcmQgY3VycmVudCBwb3NpdGlvbiBzbyBvbiBkcmFn
ZW5kIHdlIGtub3cgaWYgdGhlIGRyYWdnZWQgaXRlbSBjaGFuZ2VkIHBvc2l0aW9uIG9yIG5vdCwg
bm90IHVzaW5nIGdldEl0ZW1zIHRvIGFsbG93IGRyYWdzb3J0IHRvIHJlc3RvcmUgZHJhZ2dlZCBp
dGVtIHRvIG9yaWdpbmFsIGxvY2F0aW9uIGluIHJlbGF0aW9uIHRvIGZpeGVkIGl0ZW1zDQoJCQkJ
CWxpc3QuZHJhZ2dlZEl0ZW0uYXR0cigiZGF0YS1vcmlncG9zIiwgJCh0aGlzKS5hdHRyKCJkYXRh
LWxpc3RpZHgiKSArICItIiArICQobGlzdC5jb250YWluZXIpLmNoaWxkcmVuKCkuaW5kZXgobGlz
dC5kcmFnZ2VkSXRlbSkpOw0KDQoJCQkJCS8vY2FsY3VsYXRlIG1vdXNlIG9mZnNldCByZWxhdGl2
ZSB0byBkcmFnZ2VkSXRlbQ0KCQkJCQl2YXIgbXQgPSBwYXJzZUludChsaXN0LmRyYWdnZWRJdGVt
LmNzcygibWFyZ2luVG9wIikpOw0KCQkJCQl2YXIgbWwgPSBwYXJzZUludChsaXN0LmRyYWdnZWRJ
dGVtLmNzcygibWFyZ2luTGVmdCIpKTsNCgkJCQkJbGlzdC5vZmZzZXQgPSBsaXN0LmRyYWdnZWRJ
dGVtLm9mZnNldCgpOw0KCQkJCQlsaXN0Lm9mZnNldC50b3AgPSBlLnBhZ2VZIC0gbGlzdC5vZmZz
ZXQudG9wICsgKGlzTmFOKG10KSA/IDAgOiBtdCkgLSAxOw0KCQkJCQlsaXN0Lm9mZnNldC5sZWZ0
ID0gZS5wYWdlWCAtIGxpc3Qub2Zmc2V0LmxlZnQgKyAoaXNOYU4obWwpID8gMCA6IG1sKSAtIDE7
DQoNCgkJCQkJLy9jYWxjdWxhdGUgYm94IHRoZSBkcmFnZ2VkIGl0ZW0gY2FuJ3QgYmUgZHJhZ2dl
ZCBvdXRzaWRlIG9mDQoJCQkJCWlmICghb3B0cy5kcmFnQmV0d2Vlbikgew0KCQkJCQkJdmFyIGNv
bnRhaW5lckhlaWdodCA9ICQobGlzdC5jb250YWluZXIpLm91dGVySGVpZ2h0KCkgPT0gMCA/IE1h
dGgubWF4KDEsIE1hdGgucm91bmQoMC41ICsgbGlzdC5nZXRJdGVtcygpLmxlbmd0aCAqIGxpc3Qu
ZHJhZ2dlZEl0ZW0ub3V0ZXJXaWR0aCgpIC8gJChsaXN0LmNvbnRhaW5lcikub3V0ZXJXaWR0aCgp
KSkgKiBsaXN0LmRyYWdnZWRJdGVtLm91dGVySGVpZ2h0KCkgOiAkKGxpc3QuY29udGFpbmVyKS5v
dXRlckhlaWdodCgpOw0KCQkJCQkJbGlzdC5vZmZzZXRMaW1pdCA9ICQobGlzdC5jb250YWluZXIp
Lm9mZnNldCgpOw0KCQkJCQkJbGlzdC5vZmZzZXRMaW1pdC5yaWdodCA9IGxpc3Qub2Zmc2V0TGlt
aXQubGVmdCArICQobGlzdC5jb250YWluZXIpLm91dGVyV2lkdGgoKSAtIGxpc3QuZHJhZ2dlZEl0
ZW0ub3V0ZXJXaWR0aCgpOw0KCQkJCQkJbGlzdC5vZmZzZXRMaW1pdC5ib3R0b20gPSBsaXN0Lm9m
ZnNldExpbWl0LnRvcCArIGNvbnRhaW5lckhlaWdodCAtIGxpc3QuZHJhZ2dlZEl0ZW0ub3V0ZXJI
ZWlnaHQoKTsNCgkJCQkJfQ0KDQoJCQkJCS8vY3JlYXRlIHBsYWNlaG9sZGVyIGl0ZW0NCgkJCQkJ
dmFyIGggPSBsaXN0LmRyYWdnZWRJdGVtLmhlaWdodCgpOw0KCQkJCQl2YXIgdyA9IGxpc3QuZHJh
Z2dlZEl0ZW0ud2lkdGgoKTsNCgkJCQkJaWYgKG9wdHMudGFnTmFtZSA9PSAidHIiKSB7DQoJCQkJ
CQlsaXN0LmRyYWdnZWRJdGVtLmNoaWxkcmVuKCkuZWFjaChmdW5jdGlvbigpIHsgJCh0aGlzKS53
aWR0aCgkKHRoaXMpLndpZHRoKCkpOyB9KTsNCgkJCQkJCWxpc3QucGxhY2VIb2xkZXJJdGVtID0g
bGlzdC5kcmFnZ2VkSXRlbS5jbG9uZSgpLmF0dHIoImRhdGEtcGxhY2Vob2xkZXIiLCB0cnVlKTsN
CgkJCQkJCWxpc3QuZHJhZ2dlZEl0ZW0uYWZ0ZXIobGlzdC5wbGFjZUhvbGRlckl0ZW0pOw0KCQkJ
CQkJbGlzdC5wbGFjZUhvbGRlckl0ZW0uY2hpbGRyZW4oKS5lYWNoKGZ1bmN0aW9uKCkgeyAkKHRo
aXMpLmNzcyh7IGJvcmRlcldpZHRoOjAsIHdpZHRoOiAkKHRoaXMpLndpZHRoKCkgKyAxLCBoZWln
aHQ6ICQodGhpcykuaGVpZ2h0KCkgKyAxIH0pLmh0bWwoIiZuYnNwOyIpOyB9KTsNCgkJCQkJfSBl
bHNlIHsNCgkJCQkJCWxpc3QuZHJhZ2dlZEl0ZW0uYWZ0ZXIob3B0cy5wbGFjZUhvbGRlclRlbXBs
YXRlKTsNCgkJCQkJCWxpc3QucGxhY2VIb2xkZXJJdGVtID0gbGlzdC5kcmFnZ2VkSXRlbS5uZXh0
KCkuY3NzKHsgaGVpZ2h0OiBoLCB3aWR0aDogdyB9KS5hdHRyKCJkYXRhLXBsYWNlaG9sZGVyIiwg
dHJ1ZSk7DQoJCQkJCX0NCg0KCQkJCQlpZiAob3B0cy50YWdOYW1lID09ICJ0ZCIpIHsNCgkJCQkJ
CXZhciBsaXN0VGFibGUgPSBsaXN0LmRyYWdnZWRJdGVtLmNsb3Nlc3QoInRhYmxlIikuZ2V0KDAp
Ow0KCQkJCQkJJCgiPHRhYmxlIGlkPSciICsgbGlzdFRhYmxlLmlkICsgIicgc3R5bGU9J2JvcmRl
ci13aWR0aDogMHB4OycgY2xhc3M9J2RyYWdTb3J0SXRlbSAiICsgbGlzdFRhYmxlLmNsYXNzTmFt
ZSArICInPjx0cj48L3RyPjwvdGFibGU+IikuYXBwZW5kVG8oImJvZHkiKS5jaGlsZHJlbigpLmFw
cGVuZChsaXN0LmRyYWdnZWRJdGVtKTsNCgkJCQkJfQ0KDQoJCQkJCS8vc3R5bGUgZHJhZ2dlZEl0
ZW0gd2hpbGUgZHJhZ2dpbmcNCgkJCQkJdmFyIG9yaWcgPSBsaXN0LmRyYWdnZWRJdGVtLmF0dHIo
InN0eWxlIik7DQoJCQkJCWxpc3QuZHJhZ2dlZEl0ZW0uYXR0cigiZGF0YS1vcmlnc3R5bGUiLCBv
cmlnID8gb3JpZyA6ICIiKTsNCgkJCQkJbGlzdC5kcmFnZ2VkSXRlbS5jc3MoeyBwb3NpdGlvbjog
ImFic29sdXRlIiwgb3BhY2l0eTogMC44LCAiei1pbmRleCI6IDk5OSwgaGVpZ2h0OiBoLCB3aWR0
aDogdyB9KTsNCg0KCQkJCQkvL2F1dG8tc2Nyb2xsIHNldHVwDQoJCQkJCWxpc3Quc2Nyb2xsID0g
eyBtb3ZlWDogMCwgbW92ZVk6IDAsIG1heFg6ICQoZG9jdW1lbnQpLndpZHRoKCkgLSAkKHdpbmRv
dykud2lkdGgoKSwgbWF4WTogJChkb2N1bWVudCkuaGVpZ2h0KCkgLSAkKHdpbmRvdykuaGVpZ2h0
KCkgfTsNCgkJCQkJbGlzdC5zY3JvbGwuc2Nyb2xsWSA9IHdpbmRvdy5zZXRJbnRlcnZhbChmdW5j
dGlvbigpIHsNCgkJCQkJCWlmIChvcHRzLnNjcm9sbENvbnRhaW5lciAhPSB3aW5kb3cpIHsNCgkJ
CQkJCQkkKG9wdHMuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxUb3AoJChvcHRzLnNjcm9sbENvbnRh
aW5lcikuc2Nyb2xsVG9wKCkgKyBsaXN0LnNjcm9sbC5tb3ZlWSk7DQoJCQkJCQkJcmV0dXJuOw0K
CQkJCQkJfQ0KCQkJCQkJdmFyIHQgPSAkKG9wdHMuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxUb3Ao
KTsNCgkJCQkJCWlmIChsaXN0LnNjcm9sbC5tb3ZlWSA+IDAgJiYgdCA8IGxpc3Quc2Nyb2xsLm1h
eFkgfHwgbGlzdC5zY3JvbGwubW92ZVkgPCAwICYmIHQgPiAwKSB7DQoJCQkJCQkJJChvcHRzLnNj
cm9sbENvbnRhaW5lcikuc2Nyb2xsVG9wKHQgKyBsaXN0LnNjcm9sbC5tb3ZlWSk7DQoJCQkJCQkJ
bGlzdC5kcmFnZ2VkSXRlbS5jc3MoInRvcCIsIGxpc3QuZHJhZ2dlZEl0ZW0ub2Zmc2V0KCkudG9w
ICsgbGlzdC5zY3JvbGwubW92ZVkgKyAxKTsNCgkJCQkJCX0NCgkJCQkJfSwgMTApOw0KCQkJCQls
aXN0LnNjcm9sbC5zY3JvbGxYID0gd2luZG93LnNldEludGVydmFsKGZ1bmN0aW9uKCkgew0KCQkJ
CQkJaWYgKG9wdHMuc2Nyb2xsQ29udGFpbmVyICE9IHdpbmRvdykgew0KCQkJCQkJCSQob3B0cy5z
Y3JvbGxDb250YWluZXIpLnNjcm9sbExlZnQoJChvcHRzLnNjcm9sbENvbnRhaW5lcikuc2Nyb2xs
TGVmdCgpICsgbGlzdC5zY3JvbGwubW92ZVgpOw0KCQkJCQkJCXJldHVybjsNCgkJCQkJCX0NCgkJ
CQkJCXZhciBsID0gJChvcHRzLnNjcm9sbENvbnRhaW5lcikuc2Nyb2xsTGVmdCgpOw0KCQkJCQkJ
aWYgKGxpc3Quc2Nyb2xsLm1vdmVYID4gMCAmJiBsIDwgbGlzdC5zY3JvbGwubWF4WCB8fCBsaXN0
LnNjcm9sbC5tb3ZlWCA8IDAgJiYgbCA+IDApIHsNCgkJCQkJCQkkKG9wdHMuc2Nyb2xsQ29udGFp
bmVyKS5zY3JvbGxMZWZ0KGwgKyBsaXN0LnNjcm9sbC5tb3ZlWCk7DQoJCQkJCQkJbGlzdC5kcmFn
Z2VkSXRlbS5jc3MoImxlZnQiLCBsaXN0LmRyYWdnZWRJdGVtLm9mZnNldCgpLmxlZnQgKyBsaXN0
LnNjcm9sbC5tb3ZlWCArIDEpOw0KCQkJCQkJfQ0KCQkJCQl9LCAxMCk7DQoNCgkJCQkJLy9taXNj
DQoJCQkJCSQobGlzdHMpLmVhY2goZnVuY3Rpb24oaSwgbCkgeyBsLmNyZWF0ZURyb3BUYXJnZXRz
KCk7IGwuYnVpbGRQb3NpdGlvblRhYmxlKCk7IH0pOw0KCQkJCQlsaXN0LnNldFBvcyhlLnBhZ2VY
LCBlLnBhZ2VZKTsNCgkJCQkJJChkb2N1bWVudCkuYmluZCgibW91c2Vtb3ZlIiwgbGlzdC5zd2Fw
SXRlbXMpOw0KCQkJCQkkKGRvY3VtZW50KS5iaW5kKCJtb3VzZXVwIiwgbGlzdC5kcm9wSXRlbSk7
DQoJCQkJfSwNCg0KCQkJCS8vc2V0IHBvc2l0aW9uIG9mIGRyYWdnZWRJdGVtDQoJCQkJc2V0UG9z
OiBmdW5jdGlvbih4LCB5KSB7IA0KCQkJCQkvL3JlbW92ZSBtb3VzZSBvZmZzZXQgc28gbW91c2Ug
Y3Vyc29yIHJlbWFpbnMgaW4gc2FtZSBwbGFjZSBvbiBkcmFnZ2VkSXRlbSBpbnN0ZWFkIG9mIHRv
cCBsZWZ0IGNvcm5lcg0KCQkJCQl2YXIgdG9wID0geSAtIHRoaXMub2Zmc2V0LnRvcDsNCgkJCQkJ
dmFyIGxlZnQgPSB4IC0gdGhpcy5vZmZzZXQubGVmdDsNCg0KCQkJCQkvL2xpbWl0IHRvcCwgbGVm
dCB0byB3aXRoaW4gYm94IGRyYWdnZWRJdGVtIGNhbid0IGJlIGRyYWdnZWQgb3V0c2lkZSBvZg0K
CQkJCQlpZiAoIW9wdHMuZHJhZ0JldHdlZW4pIHsNCgkJCQkJCXRvcCA9IE1hdGgubWluKHRoaXMu
b2Zmc2V0TGltaXQuYm90dG9tLCBNYXRoLm1heCh0b3AsIHRoaXMub2Zmc2V0TGltaXQudG9wKSk7
DQoJCQkJCQlsZWZ0ID0gTWF0aC5taW4odGhpcy5vZmZzZXRMaW1pdC5yaWdodCwgTWF0aC5tYXgo
bGVmdCwgdGhpcy5vZmZzZXRMaW1pdC5sZWZ0KSk7DQoJCQkJCX0NCg0KCQkJCQkvL2FkanVzdCB0
b3AgJiBsZWZ0IGNhbGN1bGF0aW9ucyB0byBwYXJlbnQgb2Zmc2V0DQoJCQkJCXZhciBwYXJlbnQg
PSB0aGlzLmRyYWdnZWRJdGVtLm9mZnNldFBhcmVudCgpLm5vdCgiYm9keSIpLm9mZnNldCgpOyAv
L29mZnNldFBhcmVudCByZXR1cm5zIGJvZHkgZXZlbiB3aGVuIGl0J3Mgc3RhdGljLCBpZiBub3Qg
c3RhdGljIG9mZnNldCBpcyBvbmx5IGZhY3RvcmluZyBtYXJnaW4NCgkJCQkJaWYgKHBhcmVudCAh
PSBudWxsKSB7DQoJCQkJCQl0b3AgLT0gcGFyZW50LnRvcDsNCgkJCQkJCWxlZnQgLT0gcGFyZW50
LmxlZnQ7DQoJCQkJCX0NCg0KCQkJCQkvL3NldCB4IG9yIHkgYXV0by1zY3JvbGwgYW1vdW50DQoJ
CQkJCWlmIChvcHRzLnNjcm9sbENvbnRhaW5lciA9PSB3aW5kb3cpIHsNCgkJCQkJCXkgLT0gJCh3
aW5kb3cpLnNjcm9sbFRvcCgpOw0KCQkJCQkJeCAtPSAkKHdpbmRvdykuc2Nyb2xsTGVmdCgpOw0K
CQkJCQkJeSA9IE1hdGgubWF4KDAsIHkgLSAkKHdpbmRvdykuaGVpZ2h0KCkgKyA1KSArIE1hdGgu
bWluKDAsIHkgLSA1KTsNCgkJCQkJCXggPSBNYXRoLm1heCgwLCB4IC0gJCh3aW5kb3cpLndpZHRo
KCkgKyA1KSArIE1hdGgubWluKDAsIHggLSA1KTsNCgkJCQkJfSBlbHNlIHsNCgkJCQkJCXZhciBj
b250ID0gJChvcHRzLnNjcm9sbENvbnRhaW5lcik7DQoJCQkJCQl2YXIgb2Zmc2V0ID0gY29udC5v
ZmZzZXQoKTsNCgkJCQkJCXkgPSBNYXRoLm1heCgwLCB5IC0gY29udC5oZWlnaHQoKSAtIG9mZnNl
dC50b3ApICsgTWF0aC5taW4oMCwgeSAtIG9mZnNldC50b3ApOw0KCQkJCQkJeCA9IE1hdGgubWF4
KDAsIHggLSBjb250LndpZHRoKCkgLSBvZmZzZXQubGVmdCkgKyBNYXRoLm1pbigwLCB4IC0gb2Zm
c2V0LmxlZnQpOw0KCQkJCQl9DQoJCQkJCQ0KCQkJCQlsaXN0LnNjcm9sbC5tb3ZlWCA9IHggPT0g
MCA/IDAgOiB4ICogb3B0cy5zY3JvbGxTcGVlZCAvIE1hdGguYWJzKHgpOw0KCQkJCQlsaXN0LnNj
cm9sbC5tb3ZlWSA9IHkgPT0gMCA/IDAgOiB5ICogb3B0cy5zY3JvbGxTcGVlZCAvIE1hdGguYWJz
KHkpOw0KDQoJCQkJCS8vbW92ZSBkcmFnZ2VkSXRlbSB0byBuZXcgbW91c2UgY3Vyc29yIGxvY2F0
aW9uDQoJCQkJCXRoaXMuZHJhZ2dlZEl0ZW0uY3NzKHsgdG9wOiB0b3AsIGxlZnQ6IGxlZnQgfSk7
DQoJCQkJfSwNCg0KCQkJCS8vYnVpbGQgYSB0YWJsZSByZWNvcmRpbmcgYWxsIHRoZSBwb3NpdGlv
bnMgb2YgdGhlIG1vdmVhYmxlIGxpc3QgaXRlbXMNCgkJCQlidWlsZFBvc2l0aW9uVGFibGU6IGZ1
bmN0aW9uKCkgew0KCQkJCQl2YXIgcG9zID0gW107DQoJCQkJCXRoaXMuZ2V0SXRlbXMoKS5ub3Qo
W2xpc3QuZHJhZ2dlZEl0ZW1bMF0sIGxpc3QucGxhY2VIb2xkZXJJdGVtWzBdXSkuZWFjaChmdW5j
dGlvbihpKSB7DQoJCQkJCQl2YXIgbG9jID0gJCh0aGlzKS5vZmZzZXQoKTsNCgkJCQkJCWxvYy5y
aWdodCA9IGxvYy5sZWZ0ICsgJCh0aGlzKS5vdXRlcldpZHRoKCk7DQoJCQkJCQlsb2MuYm90dG9t
ID0gbG9jLnRvcCArICQodGhpcykub3V0ZXJIZWlnaHQoKTsNCgkJCQkJCWxvYy5lbG0gPSB0aGlz
Ow0KCQkJCQkJcG9zW2ldID0gbG9jOw0KCQkJCQl9KTsNCgkJCQkJdGhpcy5wb3MgPSBwb3M7DQoJ
CQkJfSwNCg0KCQkJCWRyb3BJdGVtOiBmdW5jdGlvbigpIHsNCgkJCQkJaWYgKGxpc3QuZHJhZ2dl
ZEl0ZW0gPT0gbnVsbCkNCgkJCQkJCXJldHVybjsNCg0KCQkJCQkvL2xpc3QuZHJhZ2dlZEl0ZW0u
YXR0cigic3R5bGUiLCAiIikgZG9lc24ndCB3b3JrIG9uIElFOCBhbmQgalF1ZXJ5IDEuNSBvciBs
b3dlcg0KCQkJCQkvL2xpc3QuZHJhZ2dlZEl0ZW0ucmVtb3ZlQXR0cigic3R5bGUiKSBkb2Vzbid0
IHdvcmsgb24gY2hyb21lIGFuZCBqUXVlcnkgMS42ICh3b3JrcyBqUXVlcnkgMS41IG9yIGxvd2Vy
KQ0KCQkJCQl2YXIgb3JpZyA9IGxpc3QuZHJhZ2dlZEl0ZW0uYXR0cigiZGF0YS1vcmlnc3R5bGUi
KTsNCgkJCQkJbGlzdC5kcmFnZ2VkSXRlbS5hdHRyKCJzdHlsZSIsIG9yaWcpOw0KCQkJCQlpZiAo
b3JpZyA9PSAiIikNCgkJCQkJCWxpc3QuZHJhZ2dlZEl0ZW0ucmVtb3ZlQXR0cigic3R5bGUiKTsN
CgkJCQkJbGlzdC5kcmFnZ2VkSXRlbS5yZW1vdmVBdHRyKCJkYXRhLW9yaWdzdHlsZSIpOw0KDQoJ
CQkJCWxpc3Quc3R5bGVEcmFnSGFuZGxlcnModHJ1ZSk7DQoNCgkJCQkJbGlzdC5wbGFjZUhvbGRl
ckl0ZW0uYmVmb3JlKGxpc3QuZHJhZ2dlZEl0ZW0pOw0KCQkJCQlsaXN0LnBsYWNlSG9sZGVySXRl
bS5yZW1vdmUoKTsNCg0KCQkJCQkkKCJbZGF0YS1kcm9wdGFyZ2V0XSwgLmRyYWdTb3J0SXRlbSIp
LnJlbW92ZSgpOw0KDQoJCQkJCXdpbmRvdy5jbGVhckludGVydmFsKGxpc3Quc2Nyb2xsLnNjcm9s
bFkpOw0KCQkJCQl3aW5kb3cuY2xlYXJJbnRlcnZhbChsaXN0LnNjcm9sbC5zY3JvbGxYKTsNCg0K
CQkJCQkvL2lmIHBvc2l0aW9uIGNoYW5nZWQgY2FsbCBkcmFnRW5kDQoJCQkJCWlmIChsaXN0LmRy
YWdnZWRJdGVtLmF0dHIoImRhdGEtb3JpZ3BvcyIpICE9ICQobGlzdHMpLmluZGV4KGxpc3QpICsg
Ii0iICsgJChsaXN0LmNvbnRhaW5lcikuY2hpbGRyZW4oKS5pbmRleChsaXN0LmRyYWdnZWRJdGVt
KSkNCgkJCQkJCWlmIChvcHRzLmRyYWdFbmQuYXBwbHkobGlzdC5kcmFnZ2VkSXRlbSkgPT0gZmFs
c2UpIHsgLy9pZiBkcmFnRW5kIHJldHVybnMgZmFsc2UgcmV2ZXJ0IG9yZGVyDQoJCQkJCQkJdmFy
IHBvcyA9IGxpc3QuZHJhZ2dlZEl0ZW0uYXR0cigiZGF0YS1vcmlncG9zIikuc3BsaXQoJy0nKTsN
CgkJCQkJCQl2YXIgbmV4dEl0ZW0gPSAkKGxpc3RzW3Bvc1swXV0uY29udGFpbmVyKS5jaGlsZHJl
bigpLm5vdChsaXN0LmRyYWdnZWRJdGVtKS5lcShwb3NbMV0pOw0KCQkJCQkJCWlmIChuZXh0SXRl
bS5sZW5ndGggPiAwKQ0KCQkJCQkJCQluZXh0SXRlbS5iZWZvcmUobGlzdC5kcmFnZ2VkSXRlbSk7
DQoJCQkJCQkJZWxzZSBpZiAocG9zWzFdID09IDApIC8vd2FzIHRoZSBvbmx5IGl0ZW0gaW4gbGlz
dA0KCQkJCQkJCQkkKGxpc3RzW3Bvc1swXV0uY29udGFpbmVyKS5wcmVwZW5kKGxpc3QuZHJhZ2dl
ZEl0ZW0pOw0KCQkJCQkJCWVsc2UgLy93YXMgdGhlIGxhc3QgaXRlbSBpbiBsaXN0DQoJCQkJCQkJ
CSQobGlzdHNbcG9zWzBdXS5jb250YWluZXIpLmFwcGVuZChsaXN0LmRyYWdnZWRJdGVtKTsNCgkJ
CQkJCX0NCgkJCQkJbGlzdC5kcmFnZ2VkSXRlbS5yZW1vdmVBdHRyKCJkYXRhLW9yaWdwb3MiKTsN
Cg0KCQkJCQlsaXN0LmRyYWdnZWRJdGVtID0gbnVsbDsNCgkJCQkJJChkb2N1bWVudCkudW5iaW5k
KCJtb3VzZW1vdmUiLCBsaXN0LnN3YXBJdGVtcyk7DQoJCQkJCSQoZG9jdW1lbnQpLnVuYmluZCgi
bW91c2V1cCIsIGxpc3QuZHJvcEl0ZW0pOw0KCQkJCQlyZXR1cm4gZmFsc2U7DQoJCQkJfSwNCg0K
CQkJCS8vc3dhcCB0aGUgZHJhZ2dlZEl0ZW0gKHJlcHJlc2VudGVkIHZpc3VhbGx5IGJ5IHBsYWNl
aG9sZGVyKSB3aXRoIHRoZSBsaXN0IGl0ZW0gdGhlIGl0IGhhcyBiZWVuIGRyYWdnZWQgb24gdG9w
IG9mDQoJCQkJc3dhcEl0ZW1zOiBmdW5jdGlvbihlKSB7DQoJCQkJCWlmIChsaXN0LmRyYWdnZWRJ
dGVtID09IG51bGwpDQoJCQkJCQlyZXR1cm4gZmFsc2U7DQoNCgkJCQkJLy9tb3ZlIGRyYWdnZWRJ
dGVtIHRvIG1vdXNlIGxvY2F0aW9uDQoJCQkJCWxpc3Quc2V0UG9zKGUucGFnZVgsIGUucGFnZVkp
Ow0KDQoJCQkJCS8vcmV0cmlldmUgbGlzdCBhbmQgaXRlbSBwb3NpdGlvbiBtb3VzZSBjdXJzb3Ig
aXMgb3Zlcg0KCQkJCQl2YXIgZWkgPSBsaXN0LmZpbmRQb3MoZS5wYWdlWCwgZS5wYWdlWSk7DQoJ
CQkJCXZhciBubGlzdCA9IGxpc3Q7DQoJCQkJCWZvciAodmFyIGkgPSAwOyBlaSA9PSAtMSAmJiBv
cHRzLmRyYWdCZXR3ZWVuICYmIGkgPCBsaXN0cy5sZW5ndGg7IGkrKykgew0KCQkJCQkJZWkgPSBs
aXN0c1tpXS5maW5kUG9zKGUucGFnZVgsIGUucGFnZVkpOw0KCQkJCQkJbmxpc3QgPSBsaXN0c1tp
XTsNCgkJCQkJfQ0KDQoJCQkJCS8vaWYgbm90IG92ZXIgYW5vdGhlciBtb3ZlYWJsZSBsaXN0IGl0
ZW0gcmV0dXJuDQoJCQkJCWlmIChlaSA9PSAtMSkNCgkJCQkJCXJldHVybiBmYWxzZTsNCg0KCQkJ
CQkvL3NhdmUgZml4ZWQgaXRlbXMgbG9jYXRpb25zDQoJCQkJCXZhciBjaGlsZHJlbiA9IGZ1bmN0
aW9uKCkgeyByZXR1cm4gJChubGlzdC5jb250YWluZXIpLmNoaWxkcmVuKCkubm90KG5saXN0LmRy
YWdnZWRJdGVtKTsgfTsNCgkJCQkJdmFyIGZpeGVkID0gY2hpbGRyZW4oKS5ub3Qob3B0cy5pdGVt
U2VsZWN0b3IpLmVhY2goZnVuY3Rpb24oaSkgeyB0aGlzLmlkeCA9IGNoaWxkcmVuKCkuaW5kZXgo
dGhpcyk7IH0pOw0KDQoJCQkJCS8vaWYgbW92aW5nIGRyYWdnZWRJdGVtIHVwIG9yIGxlZnQgcGxh
Y2UgcGxhY2VIb2xkZXIgYmVmb3JlIGxpc3QgaXRlbSB0aGUgZHJhZ2dlZCBpdGVtIGlzIGhvdmVy
aW5nIG92ZXIgb3RoZXJ3aXNlIHBsYWNlIGl0IGFmdGVyDQoJCQkJCWlmIChsYXN0UG9zID09IG51
bGwgfHwgbGFzdFBvcy50b3AgPiBsaXN0LmRyYWdnZWRJdGVtLm9mZnNldCgpLnRvcCB8fCBsYXN0
UG9zLmxlZnQgPiBsaXN0LmRyYWdnZWRJdGVtLm9mZnNldCgpLmxlZnQpDQoJCQkJCQkkKG5saXN0
LnBvc1tlaV0uZWxtKS5iZWZvcmUobGlzdC5wbGFjZUhvbGRlckl0ZW0pOw0KCQkJCQllbHNlDQoJ
CQkJCQkkKG5saXN0LnBvc1tlaV0uZWxtKS5hZnRlcihsaXN0LnBsYWNlSG9sZGVySXRlbSk7DQoN
CgkJCQkJLy9yZXN0b3JlIGZpeGVkIGl0ZW1zIGxvY2F0aW9uDQoJCQkJCWZpeGVkLmVhY2goZnVu
Y3Rpb24oKSB7DQoJCQkJCQl2YXIgZWxtID0gY2hpbGRyZW4oKS5lcSh0aGlzLmlkeCkuZ2V0KDAp
Ow0KCQkJCQkJaWYgKHRoaXMgIT0gZWxtICYmIGNoaWxkcmVuKCkuaW5kZXgodGhpcykgPCB0aGlz
LmlkeCkNCgkJCQkJCQkkKHRoaXMpLmluc2VydEFmdGVyKGVsbSk7DQoJCQkJCQllbHNlIGlmICh0
aGlzICE9IGVsbSkNCgkJCQkJCQkkKHRoaXMpLmluc2VydEJlZm9yZShlbG0pOw0KCQkJCQl9KTsN
Cg0KCQkJCQkvL21pc2MNCgkJCQkJJChsaXN0cykuZWFjaChmdW5jdGlvbihpLCBsKSB7IGwuY3Jl
YXRlRHJvcFRhcmdldHMoKTsgbC5idWlsZFBvc2l0aW9uVGFibGUoKTsgfSk7DQoJCQkJCWxhc3RQ
b3MgPSBsaXN0LmRyYWdnZWRJdGVtLm9mZnNldCgpOw0KCQkJCQlyZXR1cm4gZmFsc2U7DQoJCQkJ
fSwNCg0KCQkJCS8vcmV0dXJucyB0aGUgaW5kZXggb2YgdGhlIGxpc3QgaXRlbSB0aGUgbW91c2Ug
aXMgb3Zlcg0KCQkJCWZpbmRQb3M6IGZ1bmN0aW9uKHgsIHkpIHsNCgkJCQkJZm9yICh2YXIgaSA9
IDA7IGkgPCB0aGlzLnBvcy5sZW5ndGg7IGkrKykgew0KCQkJCQkJaWYgKHRoaXMucG9zW2ldLmxl
ZnQgPCB4ICYmIHRoaXMucG9zW2ldLnJpZ2h0ID4geCAmJiB0aGlzLnBvc1tpXS50b3AgPCB5ICYm
IHRoaXMucG9zW2ldLmJvdHRvbSA+IHkpDQoJCQkJCQkJcmV0dXJuIGk7DQoJCQkJCX0NCgkJCQkJ
cmV0dXJuIC0xOw0KCQkJCX0sDQoNCgkJCQkvL2NyZWF0ZSBkcm9wIHRhcmdldHMgd2hpY2ggYXJl
IHBsYWNlaG9sZGVycyBhdCB0aGUgZW5kIG9mIG90aGVyIGxpc3RzIHRvIGFsbG93IGRyYWdnaW5n
IHN0cmFpZ2h0IHRvIHRoZSBsYXN0IHBvc2l0aW9uDQoJCQkJY3JlYXRlRHJvcFRhcmdldHM6IGZ1
bmN0aW9uKCkgew0KCQkJCQlpZiAoIW9wdHMuZHJhZ0JldHdlZW4pDQoJCQkJCQlyZXR1cm47DQoN
CgkJCQkJJChsaXN0cykuZWFjaChmdW5jdGlvbigpIHsNCgkJCQkJCXZhciBwaCA9ICQodGhpcy5j
b250YWluZXIpLmZpbmQoIltkYXRhLXBsYWNlaG9sZGVyXSIpOw0KCQkJCQkJdmFyIGR0ID0gJCh0
aGlzLmNvbnRhaW5lcikuZmluZCgiW2RhdGEtZHJvcHRhcmdldF0iKTsNCgkJCQkJCWlmIChwaC5s
ZW5ndGggPiAwICYmIGR0Lmxlbmd0aCA+IDApDQoJCQkJCQkJZHQucmVtb3ZlKCk7DQoJCQkJCQll
bHNlIGlmIChwaC5sZW5ndGggPT0gMCAmJiBkdC5sZW5ndGggPT0gMCkgew0KCQkJCQkJCWlmIChv
cHRzLnRhZ05hbWUgPT0gInRkIikNCgkJCQkJCQkJJChvcHRzLnBsYWNlSG9sZGVyVGVtcGxhdGUp
LmF0dHIoImRhdGEtZHJvcHRhcmdldCIsIHRydWUpLmFwcGVuZFRvKHRoaXMuY29udGFpbmVyKTsN
CgkJCQkJCQllbHNlDQoJCQkJCQkJCS8vbGlzdC5wbGFjZUhvbGRlckl0ZW0uY2xvbmUoKS5yZW1v
dmVBdHRyKCJkYXRhLXBsYWNlaG9sZGVyIikgY3Jhc2hlcyBpbiBJRTcgYW5kIGpxdWVyeSAxLjUu
MSAoZG9lc24ndCBpbiBqcXVlcnkgMS40LjIgb3IgSUU4KQ0KCQkJCQkJCQkkKHRoaXMuY29udGFp
bmVyKS5hcHBlbmQobGlzdC5wbGFjZUhvbGRlckl0ZW0ucmVtb3ZlQXR0cigiZGF0YS1wbGFjZWhv
bGRlciIpLmNsb25lKCkuYXR0cigiZGF0YS1kcm9wdGFyZ2V0IiwgdHJ1ZSkpOw0KDQoJCQkJCQkJ
bGlzdC5wbGFjZUhvbGRlckl0ZW0uYXR0cigiZGF0YS1wbGFjZWhvbGRlciIsIHRydWUpOw0KCQkJ
CQkJfQ0KCQkJCQl9KTsNCgkJCQl9DQoJCQl9Ow0KDQoJCQluZXdMaXN0LmluaXQoKTsNCgkJCWxp
c3RzLnB1c2gobmV3TGlzdCk7DQoJCX0pOw0KDQoJCXJldHVybiB0aGlzOw0KCX07DQoNCgkkLmZu
LmRyYWdzb3J0LmRlZmF1bHRzID0gew0KCQlpdGVtU2VsZWN0b3I6ICIiLA0KCQlkcmFnU2VsZWN0
b3I6ICIiLA0KCQlkcmFnU2VsZWN0b3JFeGNsdWRlOiAiaW5wdXQsIHRleHRhcmVhIiwNCgkJZHJh
Z0VuZDogZnVuY3Rpb24oKSB7IH0sDQoJCWRyYWdCZXR3ZWVuOiBmYWxzZSwNCgkJcGxhY2VIb2xk
ZXJUZW1wbGF0ZTogIiIsDQoJCXNjcm9sbENvbnRhaW5lcjogd2luZG93LA0KCQlzY3JvbGxTcGVl
ZDogNQ0KCX07DQoNCn0pKHdpbmRvdy5qUXVlcnkpOw==
'));
}

/**
 * Perform redirects on the public site.
 *
 * @todo Is this the most efficient way of doing it?
 * @todo Set a pref for 'default_site' (could be another domain)
 */
function smd_redirect()
{
    global $pretext, $siteurl;

    $debug = gps('smd_debug');

    $the_url = parse_url($pretext['request_uri']);
    $intended = $the_url['path'] . (isset($the_url['query']) ? '?'.$the_url['query'] : '');

    if ($debug) {
        echo '++ URL / PATH TO MATCH ++';
        dmp($the_url, $intended);
    }

    // Can't use get_pref() on 404 pages *shrug*.
    $redirects = smd_redir_get(1);
    $dflt_location = get_pref('smd_redir_default_site', hu);

    foreach ($redirects as $idx => $items) {
        // Rule can't redirect to itself: ignore.
        if ($items['src'] == $items['dst']) {
            continue;
        }

        // Add pattern delimiters.
        // Detect first possible delim char that is not in use in the regex itself.
        $dlmPool = array('`', '!', '@', '|', '#', '~', '%', '/');
        $dlm = array_merge(array_diff($dlmPool, preg_split('//', $items['src'], -1)));
        $pat = (count($dlm) > 0) ? $dlm[0] . $items['src'] . $dlm[0] : $items['src'];

        if (preg_match($pat, $intended, $matches)) {
            $redir = $items['dst'];

            if ($debug) {
                echo '++ MATCHED PATTERN / REDIRECT RULE ++';
                dmp($pat, $redir);
            }

            $reps = array();

            foreach ($matches as $idx => $input) {
                if ($idx==0) continue; // Don't care about full matched pattern
                $reps['{$' . $idx . '}'] = $input;
            }

            $redir = ($redir) ? $redir : $dflt_location;
            $redir = strtr($redir, $reps);

            if ($debug) {
                echo '++ DESTINATION URL ++';
                dmp($redir);
            }

            if (!$debug) {
                ob_end_clean();
                header("HTTP/1.0 301 Moved Permanently");

                // Todo Make these a pref
                header('Cache-Control: private, no-cache, must-revalidate');
                header('Location:' . $redir, true, 301);
                die();
            }
        }
    }
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_redirect

Redirect one URL to another _without_ requiring @.htaccess@. Supports standard regular expression wildcard matches.

Add rules using _Extensions -> Redirects_. Click 'New redirect' and enter a URL portion to match against, and then a destination for that URL.

* Source and destination can either be:
** relative (no preceeding slash).
** root-relative (with preceding slash).
** absolute (full URL including domain).
* Source can be anchored if you specify the regex start (^) and/or end ($) anchor characters.
* Use an empty destination to redirect to site root (pref planned to redirect to arbitrary URL).
* Click and drag the up-down arrows to reorder the rules -- mainly for convenience since redirect chains can be created regardless of order. Redirects are processed in order, top to bottom, so if you have frequently used redirects it makes sense to put them at the top for speed reasons.
* Click the source name to edit a rule.
* Source can contain standard "preg_match":http://php.net/manual/en/function.preg-match.php patterns.
* If you wrap an expression part with parentheses it becoms available as a replacement in the destination. Replacements are indexed from 1 and denoted @{$1}@, @{$2}@ and so forth.

h2. Examples

h3(#smd_eg1). Match any string

bc(block). Source: training
Destination: _empty_

Any access to @site.com/training@ or @site.com/any/other/url/parts/training@ (or in fact any use of the word 'training' in the URL) will result in being redirected to the site home page.

h3(#smd_eg2). Match string at specific place

bc(block). Source: /training
Destination: _empty_

Any access to @site.com/training@ will redirect to home page.

h3(#smd_eg3). Relative destinations

bc(block). Source: training
Destination: archive

Redirect any access to @site.com/training@ to @site.com/archive@ instead. If accessing @site.com/some/path/to/training@ you will be redirected to @site.com/some/path/to/archive@. Note that this only works if the source is the last item on the URL. See "example 7":#smd_eg7 for a generic version to replace one part.

h3(#smd_eg4). Root-relative destinations

bc(block). Source: /training
Destination: /archive

Redirect any access to @site.com/training@ to the @site.com/archive@ section.

h3(#smd_eg5). Date-based archive

bc(block). Source: date/(\d\d)-(\d\d)-(\d{2,4})
Destination: /{$3}/{$2}/{$1}

Any URL that matches something of the form @site.com/date/DD-MM-YYYY@ (or DD-MM-YY) will redirect to @site.com/YYYY/MM/DD@. Notice that @{$N}@ matches the value of the Nth set of parentheses in the source.

h3(#smd_eg6). Remove all trailing slashes

bc(block). Source: ^/(.*)/$
Destination: /{$1}

Note that without the leading slashes your home page would probably not appear.

h3(#smd_eg7). Replace part of a URL

bc(block). Source: (.*)training(.*)
Destination: {$1}documentation{$2}

Will redirect @/some/boring/training/manual@ to @/some/boring/documentation/manual@.
# --- END PLUGIN HELP ---
-->
<?php
}
?>
