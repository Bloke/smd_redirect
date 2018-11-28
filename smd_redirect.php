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

$plugin['version'] = '0.2.0';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'https://stefdawson.com/';
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
#@language en, en-ca, en-gb, en-us
smd_redir_added => Redirect added
smd_redir_btn_new => New redirect
smd_redir_btn_pref => Prefs
smd_redir_control_panel => Control panel
smd_redir_deleting => Deleting...
smd_redir_destination => Destination
smd_redir_err_need_source => You must supply a source URL
smd_redir_saving => Saving...
smd_redir_search => Search
smd_redir_source => Source
smd_redir_tab_name => Redirects
smd_redir_updating => Updating...
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
 * @link   https://stefdawson.com/
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
    $newbtn = '<a class="navlink btnnew" href="#">' . gTxt('smd_redir_btn_new') . '</a>';
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
        var me = jQuery(this);
        me.removeClass('edited');
        ke = me.find('input[name="smd_redir_src_orig"]').val();
        vl = me.find('input[name="smd_redir_dest_orig"]').val();

        me.find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
    });
}

// Remove edit styling and save the current redirect items.
function smd_redir_save() {
    jQuery('#smd_redir_status').text('{$red_sav}');

    obj = jQuery('#smd_redirects li.edited');

    // Revert the input controls to regular text items
    ke = obj.find('input[name=smd_redir_src]').val();
    vl = obj.find('input[name=smd_redir_dest]').val();

    obj.find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
    obj.removeClass('edited');

    smd_redir_post();
}

// Remove the edited item and save the remaining redirect items.
function smd_redir_delete() {
    jQuery('#smd_redir_status').text('{$red_del}');

    obj = jQuery('#smd_redirects li.edited');
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
        if (me.hasClass('edited')) {
            var from = jQuery('#smd_redir_src').val();
            var dest = jQuery('#smd_redir_dest').val();
        } else {
            var from = me.find('.smd_redir_src').text();
            var dest = me.find('.smd_redir_dest').text();
        }

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
    jQuery('.btnnew').on('click', function(ev) {
        smd_redir_togglenew();
    });

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

    jQuery("#smd_redirects").on('click', '.smd_redir_src.closed', function() {
        var me = jQuery(this);
        me.toggleClass('closed');
        smd_redir_unedit();

        key = me.text();
        val = me.next().text();

        me.html('<label for="smd_redir_src">{$red_src}</label><input type="text" id="smd_redir_src" name="smd_redir_src" value="'+key+'" />');
        me.next().html('<label for="smd_redir_dest">{$red_dst}</label><input type="text" id="smd_redir_dest" name="smd_redir_dest" value="'+val+'" />')
            .append('<div><button type="button" id="smd_redir_save" name="smd_redir_save" onclick="smd_redir_save();">{$red_btn_sav}</button><button type="button" id="smd_redir_delete" name="smd_redir_delete" onclick="smd_redir_delete();">{$red_btn_del}</button></div>');
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
    echo '<section class="txp-details" id="smd_redir_control_panel">';
    echo '<h3 class="txp-summary lever'.(get_pref('pane_smd_redir_cpanel_visible') ? ' expanded' : '').'"><a href="#smd_redir_cpanel">' . gTxt('smd_redir_control_panel') . '</a></h3><div class="toggle" id="smd_redir_cpanel" role="region" style="display:'.(get_pref('pane_smd_redir_cpanel_visible') ? 'block' : 'none').'">';

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
    echo '</section>';

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
 * A base64-encoded version of DragSort (https://github.com/agavazov/dragsort).
 */
function smd_redir_dragdrop()
{
    return script_js(base64_decode('
IWZ1bmN0aW9uKGUpe2UuZm4uZHJhZ3NvcnQ9ZnVuY3Rpb24odCl7aWYoImRlc3Ryb3kiIT10KXt2
YXIgcj1lLmV4dGVuZCh7fSxlLmZuLmRyYWdzb3J0LmRlZmF1bHRzLHQpLG89W10sYT1udWxsLGk9
bnVsbDtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKHQsbil7ZShuKS5pcygidGFibGUiKSYmMT09
ZShuKS5jaGlsZHJlbigpLmxlbmd0aCYmZShuKS5jaGlsZHJlbigpLmlzKCJ0Ym9keSIpJiYobj1l
KG4pLmNoaWxkcmVuKCkuZ2V0KDApKTt2YXIgZD17ZHJhZ2dlZEl0ZW06bnVsbCxwbGFjZUhvbGRl
ckl0ZW06bnVsbCxwb3M6bnVsbCxvZmZzZXQ6bnVsbCxvZmZzZXRMaW1pdDpudWxsLHNjcm9sbDpu
dWxsLGNvbnRhaW5lcjpuLGluaXQ6ZnVuY3Rpb24oKXtyLnRhZ05hbWU9MD09ZSh0aGlzLmNvbnRh
aW5lcikuY2hpbGRyZW4oKS5sZW5ndGg/ImxpIjplKHRoaXMuY29udGFpbmVyKS5jaGlsZHJlbigp
LmdldCgwKS50YWdOYW1lLnRvTG93ZXJDYXNlKCksci5pdGVtU2VsZWN0b3J8fChyLml0ZW1TZWxl
Y3Rvcj1yLnRhZ05hbWUpLHIuZHJhZ1NlbGVjdG9yfHwoci5kcmFnU2VsZWN0b3I9ci50YWdOYW1l
KSxyLnBsYWNlSG9sZGVyVGVtcGxhdGV8fChyLnBsYWNlSG9sZGVyVGVtcGxhdGU9IjwiK3IudGFn
TmFtZSsiPiZuYnNwOzwvIityLnRhZ05hbWUrIj4iKSxlKHRoaXMuY29udGFpbmVyKS5hdHRyKCJk
YXRhLWxpc3RpZHgiLHQpLmJpbmQoIm1vdXNlZG93biB0b3VjaHN0YXJ0Iix0aGlzLmdyYWJJdGVt
KS5iaW5kKCJkcmFnc29ydC11bmluaXQiLHRoaXMudW5pbml0KSx0aGlzLnN0eWxlRHJhZ0hhbmRs
ZXJzKCEwKX0sdW5pbml0OmZ1bmN0aW9uKCl7dmFyIHQ9b1tlKHRoaXMpLmF0dHIoImRhdGEtbGlz
dGlkeCIpXTtlKHQuY29udGFpbmVyKS51bmJpbmQoIm1vdXNlZG93biB0b3VjaHN0YXJ0Iix0Lmdy
YWJJdGVtKS51bmJpbmQoImRyYWdzb3J0LXVuaW5pdCIpLHQuc3R5bGVEcmFnSGFuZGxlcnMoITEp
fSxnZXRJdGVtczpmdW5jdGlvbigpe3JldHVybiBlKHRoaXMuY29udGFpbmVyKS5jaGlsZHJlbihy
Lml0ZW1TZWxlY3Rvcil9LHN0eWxlRHJhZ0hhbmRsZXJzOmZ1bmN0aW9uKHQpe3RoaXMuZ2V0SXRl
bXMoKS5tYXAoZnVuY3Rpb24oKXtyZXR1cm4gZSh0aGlzKS5pcyhyLmRyYWdTZWxlY3Rvcik/dGhp
czplKHRoaXMpLmZpbmQoci5kcmFnU2VsZWN0b3IpLmdldCgpfSkuY3NzKCJjdXJzb3IiLHQ/ci5j
dXJzb3I6ImRlZmF1bHQiKX0sZ3JhYkl0ZW06ZnVuY3Rpb24odCl7dmFyIGE9b1tlKHRoaXMpLmF0
dHIoImRhdGEtbGlzdGlkeCIpXSxpPWUodC50YXJnZXQpLmNsb3Nlc3QoIltkYXRhLWxpc3RpZHhd
ID4gIityLnRhZ05hbWUpLmdldCgwKSxuPWEuZ2V0SXRlbXMoKS5maWx0ZXIoZnVuY3Rpb24oKXty
ZXR1cm4gdGhpcz09aX0pLmxlbmd0aD4wO2lmKCEoMSE9dC53aGljaCYmMCE9dC53aGljaHx8ZSh0
LnRhcmdldCkuaXMoci5kcmFnU2VsZWN0b3JFeGNsdWRlKXx8ZSh0LnRhcmdldCkuY2xvc2VzdChy
LmRyYWdTZWxlY3RvckV4Y2x1ZGUpLmxlbmd0aD4wKSYmbil7dC5wcmV2ZW50RGVmYXVsdCgpO2Zv
cih2YXIgZD10LnRhcmdldDshZShkKS5pcyhyLmRyYWdTZWxlY3Rvcik7KXtpZihkPT10aGlzKXJl
dHVybjtkPWQucGFyZW50Tm9kZX1lKGQpLmF0dHIoImRhdGEtY3Vyc29yIixlKGQpLmNzcygiY3Vy
c29yIikpLGUoZCkuY3NzKCJjdXJzb3IiLCJtb3ZlIik7dmFyIGw9dGhpcyxzPWZ1bmN0aW9uKCl7
YS5kcmFnU3RhcnQuY2FsbChsLHQpLGUoYS5jb250YWluZXIpLnVuYmluZCgibW91c2Vtb3ZlIHRv
dWNobW92ZSIscyl9O2UoYS5jb250YWluZXIpLmJpbmQoIm1vdXNlbW92ZSB0b3VjaG1vdmUiLHMp
LmJpbmQoIm1vdXNldXAgdG91Y2hlbmQiLGZ1bmN0aW9uKCl7ZShhLmNvbnRhaW5lcikudW5iaW5k
KCJtb3VzZW1vdmUgdG91Y2htb3ZlIixzKSxlKGQpLmNzcygiY3Vyc29yIixlKGQpLmF0dHIoImRh
dGEtY3Vyc29yIikpfSl9fSxkcmFnU3RhcnQ6ZnVuY3Rpb24odCl7bnVsbCE9YSYmbnVsbCE9YS5k
cmFnZ2VkSXRlbSYmYS5kcm9wSXRlbSgpLHQuY2hhbmdlZFRvdWNoZXMmJnQuY2hhbmdlZFRvdWNo
ZXNbMF0mJih0LnBhZ2VYPXQuY2hhbmdlZFRvdWNoZXNbMF0ucGFnZVgsdC5wYWdlWT10LmNoYW5n
ZWRUb3VjaGVzWzBdLnBhZ2VZKSwoYT1vW2UodGhpcykuYXR0cigiZGF0YS1saXN0aWR4IildKS5k
cmFnZ2VkSXRlbT1lKHQudGFyZ2V0KS5jbG9zZXN0KCJbZGF0YS1saXN0aWR4XSA+ICIrci50YWdO
YW1lKSxhLmRyYWdnZWRJdGVtLmF0dHIoImRhdGEtb3JpZ3BvcyIsZSh0aGlzKS5hdHRyKCJkYXRh
LWxpc3RpZHgiKSsiLSIrZShhLmNvbnRhaW5lcikuY2hpbGRyZW4oKS5pbmRleChhLmRyYWdnZWRJ
dGVtKSk7dmFyIGk9cGFyc2VJbnQoYS5kcmFnZ2VkSXRlbS5jc3MoIm1hcmdpblRvcCIpKSxuPXBh
cnNlSW50KGEuZHJhZ2dlZEl0ZW0uY3NzKCJtYXJnaW5MZWZ0IikpO2lmKGEub2Zmc2V0PWEuZHJh
Z2dlZEl0ZW0ub2Zmc2V0KCksYS5vZmZzZXQudG9wPXQucGFnZVktYS5vZmZzZXQudG9wKyhpc05h
TihpKT8wOmkpLTEsYS5vZmZzZXQubGVmdD10LnBhZ2VYLWEub2Zmc2V0LmxlZnQrKGlzTmFOKG4p
PzA6biktMSwhci5kcmFnQmV0d2Vlbil7dmFyIGQ9MD09ZShhLmNvbnRhaW5lcikub3V0ZXJIZWln
aHQoKT9NYXRoLm1heCgxLE1hdGgucm91bmQoLjUrYS5nZXRJdGVtcygpLmxlbmd0aCphLmRyYWdn
ZWRJdGVtLm91dGVyV2lkdGgoKS9lKGEuY29udGFpbmVyKS5vdXRlcldpZHRoKCkpKSphLmRyYWdn
ZWRJdGVtLm91dGVySGVpZ2h0KCk6ZShhLmNvbnRhaW5lcikub3V0ZXJIZWlnaHQoKTthLm9mZnNl
dExpbWl0PWUoYS5jb250YWluZXIpLm9mZnNldCgpLGEub2Zmc2V0TGltaXQucmlnaHQ9YS5vZmZz
ZXRMaW1pdC5sZWZ0K2UoYS5jb250YWluZXIpLm91dGVyV2lkdGgoKS1hLmRyYWdnZWRJdGVtLm91
dGVyV2lkdGgoKSxhLm9mZnNldExpbWl0LmJvdHRvbT1hLm9mZnNldExpbWl0LnRvcCtkLWEuZHJh
Z2dlZEl0ZW0ub3V0ZXJIZWlnaHQoKX12YXIgbD1hLmRyYWdnZWRJdGVtLmhlaWdodCgpLHM9YS5k
cmFnZ2VkSXRlbS53aWR0aCgpO2lmKCJ0ciI9PXIudGFnTmFtZT8oYS5kcmFnZ2VkSXRlbS5jaGls
ZHJlbigpLmVhY2goZnVuY3Rpb24oKXtlKHRoaXMpLndpZHRoKGUodGhpcykud2lkdGgoKSl9KSxh
LnBsYWNlSG9sZGVySXRlbT1hLmRyYWdnZWRJdGVtLmNsb25lKCkuYXR0cigiZGF0YS1wbGFjZWhv
bGRlciIsITApLGEuZHJhZ2dlZEl0ZW0uYWZ0ZXIoYS5wbGFjZUhvbGRlckl0ZW0pLGEucGxhY2VI
b2xkZXJJdGVtLmNoaWxkcmVuKCkuZWFjaChmdW5jdGlvbigpe2UodGhpcykuY3NzKHtib3JkZXJX
aWR0aDowLHdpZHRoOmUodGhpcykud2lkdGgoKSsxLGhlaWdodDplKHRoaXMpLmhlaWdodCgpKzF9
KS5odG1sKCImbmJzcDsiKX0pKTooYS5kcmFnZ2VkSXRlbS5hZnRlcihyLnBsYWNlSG9sZGVyVGVt
cGxhdGUpLGEucGxhY2VIb2xkZXJJdGVtPWEuZHJhZ2dlZEl0ZW0ubmV4dCgpLmNzcyh7aGVpZ2h0
Omwsd2lkdGg6c30pLmF0dHIoImRhdGEtcGxhY2Vob2xkZXIiLCEwKSksInRkIj09ci50YWdOYW1l
KXt2YXIgYz1hLmRyYWdnZWRJdGVtLmNsb3Nlc3QoInRhYmxlIikuZ2V0KDApO2UoJzx0YWJsZSBp
ZD0iJytjLmlkKyciIHN0eWxlPSJib3JkZXItd2lkdGg6IDBweDsiIGNsYXNzPSJkcmFnU29ydEl0
ZW0gJytjLmNsYXNzTmFtZSsnIj48dHI+PC90cj48L3RhYmxlPicpLmFwcGVuZFRvKCJib2R5Iiku
Y2hpbGRyZW4oKS5hcHBlbmQoYS5kcmFnZ2VkSXRlbSl9dmFyIGc9YS5kcmFnZ2VkSXRlbS5hdHRy
KCJzdHlsZSIpO2EuZHJhZ2dlZEl0ZW0uYXR0cigiZGF0YS1vcmlnc3R5bGUiLGd8fCIiKSxhLmRy
YWdnZWRJdGVtLmNzcyh7cG9zaXRpb246ImFic29sdXRlIixvcGFjaXR5Oi44LCJ6LWluZGV4Ijo5
OTksaGVpZ2h0Omwsd2lkdGg6c30pLGEuc2Nyb2xsPXttb3ZlWDowLG1vdmVZOjAsbWF4WDplKGRv
Y3VtZW50KS53aWR0aCgpLWUod2luZG93KS53aWR0aCgpLG1heFk6ZShkb2N1bWVudCkuaGVpZ2h0
KCktZSh3aW5kb3cpLmhlaWdodCgpfSxhLnNjcm9sbC5zY3JvbGxZPXdpbmRvdy5zZXRJbnRlcnZh
bChmdW5jdGlvbigpe2lmKHIuc2Nyb2xsQ29udGFpbmVyPT13aW5kb3cpe3ZhciB0PWUoci5zY3Jv
bGxDb250YWluZXIpLnNjcm9sbFRvcCgpOyhhLnNjcm9sbC5tb3ZlWT4wJiZ0PGEuc2Nyb2xsLm1h
eFl8fGEuc2Nyb2xsLm1vdmVZPDAmJnQ+MCkmJihlKHIuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxU
b3AodCthLnNjcm9sbC5tb3ZlWSksYS5kcmFnZ2VkSXRlbS5jc3MoInRvcCIsYS5kcmFnZ2VkSXRl
bS5vZmZzZXQoKS50b3ArYS5zY3JvbGwubW92ZVkrMSkpfWVsc2UgZShyLnNjcm9sbENvbnRhaW5l
cikuc2Nyb2xsVG9wKGUoci5zY3JvbGxDb250YWluZXIpLnNjcm9sbFRvcCgpK2Euc2Nyb2xsLm1v
dmVZKX0sMTApLGEuc2Nyb2xsLnNjcm9sbFg9d2luZG93LnNldEludGVydmFsKGZ1bmN0aW9uKCl7
aWYoci5zY3JvbGxDb250YWluZXI9PXdpbmRvdyl7dmFyIHQ9ZShyLnNjcm9sbENvbnRhaW5lciku
c2Nyb2xsTGVmdCgpOyhhLnNjcm9sbC5tb3ZlWD4wJiZ0PGEuc2Nyb2xsLm1heFh8fGEuc2Nyb2xs
Lm1vdmVYPDAmJnQ+MCkmJihlKHIuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxMZWZ0KHQrYS5zY3Jv
bGwubW92ZVgpLGEuZHJhZ2dlZEl0ZW0uY3NzKCJsZWZ0IixhLmRyYWdnZWRJdGVtLm9mZnNldCgp
LmxlZnQrYS5zY3JvbGwubW92ZVgrMSkpfWVsc2UgZShyLnNjcm9sbENvbnRhaW5lcikuc2Nyb2xs
TGVmdChlKHIuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxMZWZ0KCkrYS5zY3JvbGwubW92ZVgpfSwx
MCksZShvKS5lYWNoKGZ1bmN0aW9uKGUsdCl7dC5jcmVhdGVEcm9wVGFyZ2V0cygpLHQuYnVpbGRQ
b3NpdGlvblRhYmxlKCl9KSxhLnNldFBvcyh0LnBhZ2VYLHQucGFnZVkpLGUoZG9jdW1lbnQpLmJp
bmQoIm1vdXNlbW92ZSB0b3VjaG1vdmUiLGEuc3dhcEl0ZW1zKSxlKGRvY3VtZW50KS5iaW5kKCJt
b3VzZXVwIHRvdWNoZW5kIixhLmRyb3BJdGVtKSxyLnNjcm9sbENvbnRhaW5lciE9d2luZG93JiZl
KHdpbmRvdykuYmluZCgid2hlZWwiLGEud2hlZWwpfSxzZXRQb3M6ZnVuY3Rpb24odCxvKXt2YXIg
aT1vLXRoaXMub2Zmc2V0LnRvcCxuPXQtdGhpcy5vZmZzZXQubGVmdDtyLmRyYWdCZXR3ZWVufHwo
aT1NYXRoLm1pbih0aGlzLm9mZnNldExpbWl0LmJvdHRvbSxNYXRoLm1heChpLHRoaXMub2Zmc2V0
TGltaXQudG9wKSksbj1NYXRoLm1pbih0aGlzLm9mZnNldExpbWl0LnJpZ2h0LE1hdGgubWF4KG4s
dGhpcy5vZmZzZXRMaW1pdC5sZWZ0KSkpO3ZhciBkPXRoaXMuZHJhZ2dlZEl0ZW0ub2Zmc2V0UGFy
ZW50KCkubm90KCJib2R5Iikub2Zmc2V0KCk7aWYobnVsbCE9ZCYmKGktPWQudG9wLG4tPWQubGVm
dCksci5zY3JvbGxDb250YWluZXI9PXdpbmRvdylvLT1lKHdpbmRvdykuc2Nyb2xsVG9wKCksdC09
ZSh3aW5kb3cpLnNjcm9sbExlZnQoKSxvPU1hdGgubWF4KDAsby1lKHdpbmRvdykuaGVpZ2h0KCkr
NSkrTWF0aC5taW4oMCxvLTUpLHQ9TWF0aC5tYXgoMCx0LWUod2luZG93KS53aWR0aCgpKzUpK01h
dGgubWluKDAsdC01KTtlbHNle3ZhciBsPWUoci5zY3JvbGxDb250YWluZXIpLHM9bC5vZmZzZXQo
KTtvPU1hdGgubWF4KDAsby1sLmhlaWdodCgpLXMudG9wKStNYXRoLm1pbigwLG8tcy50b3ApLHQ9
TWF0aC5tYXgoMCx0LWwud2lkdGgoKS1zLmxlZnQpK01hdGgubWluKDAsdC1zLmxlZnQpfWEuc2Ny
b2xsLm1vdmVYPTA9PXQ/MDp0KnIuc2Nyb2xsU3BlZWQvTWF0aC5hYnModCksYS5zY3JvbGwubW92
ZVk9MD09bz8wOm8qci5zY3JvbGxTcGVlZC9NYXRoLmFicyhvKSx0aGlzLmRyYWdnZWRJdGVtLmNz
cyh7dG9wOmksbGVmdDpufSl9LHdoZWVsOmZ1bmN0aW9uKHQpe2lmKGEmJnIuc2Nyb2xsQ29udGFp
bmVyIT13aW5kb3cpe3ZhciBvPWUoci5zY3JvbGxDb250YWluZXIpLGk9by5vZmZzZXQoKTtpZigo
dD10Lm9yaWdpbmFsRXZlbnQpLmNsaWVudFg+aS5sZWZ0JiZ0LmNsaWVudFg8aS5sZWZ0K28ud2lk
dGgoKSYmdC5jbGllbnRZPmkudG9wJiZ0LmNsaWVudFk8aS50b3Arby5oZWlnaHQoKSl7dmFyIG49
KDA9PXQuZGVsdGFNb2RlPzE6MTApKnQuZGVsdGFZO28uc2Nyb2xsVG9wKG8uc2Nyb2xsVG9wKCkr
biksdC5wcmV2ZW50RGVmYXVsdCgpfX19LGJ1aWxkUG9zaXRpb25UYWJsZTpmdW5jdGlvbigpe3Zh
ciB0PVtdO3RoaXMuZ2V0SXRlbXMoKS5ub3QoW2EuZHJhZ2dlZEl0ZW1bMF0sYS5wbGFjZUhvbGRl
ckl0ZW1bMF1dKS5lYWNoKGZ1bmN0aW9uKHIpe3ZhciBvPWUodGhpcykub2Zmc2V0KCk7by5yaWdo
dD1vLmxlZnQrZSh0aGlzKS5vdXRlcldpZHRoKCksby5ib3R0b209by50b3ArZSh0aGlzKS5vdXRl
ckhlaWdodCgpLG8uZWxtPXRoaXMsdFtyXT1vfSksdGhpcy5wb3M9dH0sZHJvcEl0ZW06ZnVuY3Rp
b24oKXtpZihudWxsIT1hLmRyYWdnZWRJdGVtKXt2YXIgdD1hLmRyYWdnZWRJdGVtLmF0dHIoImRh
dGEtb3JpZ3N0eWxlIik7aWYoYS5kcmFnZ2VkSXRlbS5hdHRyKCJzdHlsZSIsdCksIiI9PXQmJmEu
ZHJhZ2dlZEl0ZW0ucmVtb3ZlQXR0cigic3R5bGUiKSxhLmRyYWdnZWRJdGVtLnJlbW92ZUF0dHIo
ImRhdGEtb3JpZ3N0eWxlIiksYS5zdHlsZURyYWdIYW5kbGVycyghMCksYS5wbGFjZUhvbGRlckl0
ZW0uYmVmb3JlKGEuZHJhZ2dlZEl0ZW0pLGEucGxhY2VIb2xkZXJJdGVtLnJlbW92ZSgpLGUoIltk
YXRhLWRyb3B0YXJnZXRdLCAuZHJhZ1NvcnRJdGVtIikucmVtb3ZlKCksd2luZG93LmNsZWFySW50
ZXJ2YWwoYS5zY3JvbGwuc2Nyb2xsWSksd2luZG93LmNsZWFySW50ZXJ2YWwoYS5zY3JvbGwuc2Ny
b2xsWCksYS5kcmFnZ2VkSXRlbS5hdHRyKCJkYXRhLW9yaWdwb3MiKSE9ZShvKS5pbmRleChhKSsi
LSIrZShhLmNvbnRhaW5lcikuY2hpbGRyZW4oKS5pbmRleChhLmRyYWdnZWRJdGVtKSYmMD09ci5k
cmFnRW5kLmFwcGx5KGEuZHJhZ2dlZEl0ZW0pKXt2YXIgaT1hLmRyYWdnZWRJdGVtLmF0dHIoImRh
dGEtb3JpZ3BvcyIpLnNwbGl0KCItIiksbj1lKG9baVswXV0uY29udGFpbmVyKS5jaGlsZHJlbigp
Lm5vdChhLmRyYWdnZWRJdGVtKS5lcShpWzFdKTtuLmxlbmd0aD4wP24uYmVmb3JlKGEuZHJhZ2dl
ZEl0ZW0pOjA9PWlbMV0/ZShvW2lbMF1dLmNvbnRhaW5lcikucHJlcGVuZChhLmRyYWdnZWRJdGVt
KTplKG9baVswXV0uY29udGFpbmVyKS5hcHBlbmQoYS5kcmFnZ2VkSXRlbSl9cmV0dXJuIGEuZHJh
Z2dlZEl0ZW0ucmVtb3ZlQXR0cigiZGF0YS1vcmlncG9zIiksYS5kcmFnZ2VkSXRlbT1udWxsLGUo
ZG9jdW1lbnQpLnVuYmluZCgibW91c2Vtb3ZlIHRvdWNobW92ZSIsYS5zd2FwSXRlbXMpLGUoZG9j
dW1lbnQpLnVuYmluZCgibW91c2V1cCB0b3VjaGVuZCIsYS5kcm9wSXRlbSksci5zY3JvbGxDb250
YWluZXIhPXdpbmRvdyYmZSh3aW5kb3cpLnVuYmluZCgid2hlZWwiLGEud2hlZWwpLCExfX0sc3dh
cEl0ZW1zOmZ1bmN0aW9uKHQpe2lmKG51bGw9PWEuZHJhZ2dlZEl0ZW0pcmV0dXJuITE7dC5jaGFu
Z2VkVG91Y2hlcyYmdC5jaGFuZ2VkVG91Y2hlc1swXSYmKHQucGFnZVg9dC5jaGFuZ2VkVG91Y2hl
c1swXS5wYWdlWCx0LnBhZ2VZPXQuY2hhbmdlZFRvdWNoZXNbMF0ucGFnZVkpLGEuc2V0UG9zKHQu
cGFnZVgsdC5wYWdlWSk7Zm9yKHZhciBuPWEuZmluZFBvcyh0LnBhZ2VYLHQucGFnZVkpLGQ9YSxs
PTA7LTE9PW4mJnIuZHJhZ0JldHdlZW4mJmw8by5sZW5ndGg7bCsrKW49b1tsXS5maW5kUG9zKHQu
cGFnZVgsdC5wYWdlWSksZD1vW2xdO2lmKC0xPT1uKXJldHVybiExO3ZhciBzPWZ1bmN0aW9uKCl7
cmV0dXJuIGUoZC5jb250YWluZXIpLmNoaWxkcmVuKCkubm90KGQuZHJhZ2dlZEl0ZW0pfSxjPXMo
KS5ub3Qoci5pdGVtU2VsZWN0b3IpLmVhY2goZnVuY3Rpb24oZSl7dGhpcy5pZHg9cygpLmluZGV4
KHRoaXMpfSk7cmV0dXJuIG51bGw9PWl8fGkudG9wPmEuZHJhZ2dlZEl0ZW0ub2Zmc2V0KCkudG9w
fHxpLmxlZnQ+YS5kcmFnZ2VkSXRlbS5vZmZzZXQoKS5sZWZ0P2UoZC5wb3Nbbl0uZWxtKS5iZWZv
cmUoYS5wbGFjZUhvbGRlckl0ZW0pOmUoZC5wb3Nbbl0uZWxtKS5hZnRlcihhLnBsYWNlSG9sZGVy
SXRlbSksYy5lYWNoKGZ1bmN0aW9uKCl7dmFyIHQ9cygpLmVxKHRoaXMuaWR4KS5nZXQoMCk7dGhp
cyE9dCYmcygpLmluZGV4KHRoaXMpPHRoaXMuaWR4P2UodGhpcykuaW5zZXJ0QWZ0ZXIodCk6dGhp
cyE9dCYmZSh0aGlzKS5pbnNlcnRCZWZvcmUodCl9KSxlKG8pLmVhY2goZnVuY3Rpb24oZSx0KXt0
LmNyZWF0ZURyb3BUYXJnZXRzKCksdC5idWlsZFBvc2l0aW9uVGFibGUoKX0pLGk9YS5kcmFnZ2Vk
SXRlbS5vZmZzZXQoKSwhMX0sZmluZFBvczpmdW5jdGlvbihlLHQpe2Zvcih2YXIgcj0wO3I8dGhp
cy5wb3MubGVuZ3RoO3IrKylpZih0aGlzLnBvc1tyXS5sZWZ0PGUmJnRoaXMucG9zW3JdLnJpZ2h0
PmUmJnRoaXMucG9zW3JdLnRvcDx0JiZ0aGlzLnBvc1tyXS5ib3R0b20+dClyZXR1cm4gcjtyZXR1
cm4tMX0sY3JlYXRlRHJvcFRhcmdldHM6ZnVuY3Rpb24oKXtyLmRyYWdCZXR3ZWVuJiZlKG8pLmVh
Y2goZnVuY3Rpb24oKXt2YXIgdD1lKHRoaXMuY29udGFpbmVyKS5maW5kKCJbZGF0YS1wbGFjZWhv
bGRlcl0iKSxvPWUodGhpcy5jb250YWluZXIpLmZpbmQoIltkYXRhLWRyb3B0YXJnZXRdIik7dC5s
ZW5ndGg+MCYmby5sZW5ndGg+MD9vLnJlbW92ZSgpOjA9PXQubGVuZ3RoJiYwPT1vLmxlbmd0aCYm
KCJ0ZCI9PXIudGFnTmFtZT9lKHIucGxhY2VIb2xkZXJUZW1wbGF0ZSkuYXR0cigiZGF0YS1kcm9w
dGFyZ2V0IiwhMCkuYXBwZW5kVG8odGhpcy5jb250YWluZXIpOmUodGhpcy5jb250YWluZXIpLmFw
cGVuZChhLnBsYWNlSG9sZGVySXRlbS5yZW1vdmVBdHRyKCJkYXRhLXBsYWNlaG9sZGVyIikuY2xv
bmUoKS5hdHRyKCJkYXRhLWRyb3B0YXJnZXQiLCEwKSksYS5wbGFjZUhvbGRlckl0ZW0uYXR0cigi
ZGF0YS1wbGFjZWhvbGRlciIsITApKX0pfX07ZC5pbml0KCksby5wdXNoKGQpfSksdGhpc31lKHRo
aXMpLnRyaWdnZXIoImRyYWdzb3J0LXVuaW5pdCIpfSxlLmZuLmRyYWdzb3J0LmRlZmF1bHRzPXtp
dGVtU2VsZWN0b3I6IiIsZHJhZ1NlbGVjdG9yOiIiLGRyYWdTZWxlY3RvckV4Y2x1ZGU6ImlucHV0
LCB0ZXh0YXJlYSIsZHJhZ0VuZDpmdW5jdGlvbigpe30sZHJhZ0JldHdlZW46ITEscGxhY2VIb2xk
ZXJUZW1wbGF0ZToiIixzY3JvbGxDb250YWluZXI6d2luZG93LHNjcm9sbFNwZWVkOjUsY3Vyc29y
OiJwb2ludGVyIn19KGpRdWVyeSk7
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