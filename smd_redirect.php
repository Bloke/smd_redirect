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

$plugin['version'] = '0.10';
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

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

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
//TODO: when deleting more than one at once, only the first is actually deleted
if (@ztxpinterface == 'admin') {
	global $smd_redir_event, $smd_redir_styles;
	$smd_redir_event = 'smd_redir';

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

	add_privs($smd_redir_event, '1');
	register_tab('extensions', $smd_redir_event, smd_redir_gTxt('smd_redir_tab_name'));
	register_callback('smd_redir_dispatcher', $smd_redir_event);

} elseif (@txpinterface == 'public') {
	register_callback('smd_redirect', 'pretext_end');
}

// ********************
// ADMIN SIDE INTERFACE
// ********************
// Jump off point for event/steps
function smd_redir_dispatcher($evt, $stp) {
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
	} else if (!$stp or !bouncer($stp, $available_steps)) {
		$stp = $smd_redir_event;
	}
	$stp();
}

// Main admin interface
function smd_redir($msg='') {
	global $smd_redir_event, $smd_redir_styles;

	pagetop(smd_redir_gTxt('smd_redir_tab_name'), $msg);

	// Grab the latest redirect points
	$redirects = smd_redir_get(1);

	// Set up the buttons and column info
	$newbtn = '<a class="navlink" href="#" onclick="return smd_redir_togglenew();">'.smd_redir_gTxt('smd_redir_btn_new').'</a>';
	$prefbtn = '<a class="navlink btnpref" href="?event='.$smd_redir_event.a.'step=smd_redir_prefs">'.smd_redir_gTxt('smd_redir_btn_pref').'</a>';
	$status = '<span id="smd_redir_status"></span>';

	$qs = array(
		"event" => $smd_redir_event,
	);

	$qsVars = "index.php".join_qs($qs);

	// i18n values for javascript
	$red_src = smd_redir_gTxt('smd_redir_source');
	$red_dst = smd_redir_gTxt('smd_redir_destination');
	$red_del = smd_redir_gTxt('smd_redir_deleting');
	$red_sav = smd_redir_gTxt('smd_redir_saving');
	$red_upd = smd_redir_gTxt('smd_redir_updating');
	$red_btn_del = gTxt('delete');
	$red_btn_sav = gTxt('save');

		echo <<<EOC
<script type="text/javascript">
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

// Remove edit styling
function smd_redir_unedit() {
	jQuery('#smd_redirects li.edited').each(function() {
		jQuery(this).removeClass('edited');
		ke = jQuery(this).find('input[name="smd_redir_src_orig"]').val();
		vl = jQuery(this).find('input[name="smd_redir_dest_orig"]').val();

		jQuery(this).find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
	});
}

// Remove edit styling and save the current redirect items
function smd_redir_save(pos) {
	jQuery('#smd_redir_status').text('{$red_sav}');

	// Would love to pass the object in directly, meh, can't figure it out
	obj = jQuery('#smd_redirects li[data-itemidx="'+pos+'"]');

	// Revert the input controls to regular text items
	ke = obj.find('input[name="smd_redir_src"]').val();
	vl = obj.find('input[name="smd_redir_dest"]').val();
	obj.find('.smd_redir_item').html('<div class="smd_redir_src closed">' + ke + '</div><div class="smd_redir_dest">' + vl + '</div>');
	obj.removeClass('edited');

	smd_redir_post();
}

// Remove the given item and save the remaining redirect items
function smd_redir_delete(pos) {
	jQuery('#smd_redir_status').text('{$red_del}');

	// Would love to pass the object in directly, meh, can't figure it out
	obj = jQuery('#smd_redirects li[data-itemidx="'+pos+'"]');
	obj.remove();

	smd_redir_post();
}

// Save the redirect list to the prefs
function smd_redir_post() {
	// Loop over the entire redirects collection, extract the original source, the new source and the destination,
	// then stuff them in a dedicated DOM element...
	jQuery('#smd_redirects li').each(function() {
		orig = jQuery(this).find('input[name="smd_redir_src_orig"]').val();
		from = jQuery(this).find('.smd_redir_src').text();
		dest = jQuery(this).find('.smd_redir_dest').text();

		jQuery('#smd_redir_data').data(orig, { from: from, dest: dest });
	});

	// ... and send the entire lot off to be stored
	// TODO: handle timeout/failure etc
	jQuery.post('{$qsVars}', { step: "smd_redir_save", smd_redir_data: JSON.stringify(jQuery('#smd_redir_data').data()), _txp_token : textpattern._txp_token },
		function(data) {
			jQuery('#smd_redir_status').text('');
			// Retrigger the search in case the results have changed after edit
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

	jQuery(".smd_redir_src.closed").live("click", function() {
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

</script>
EOC;

	// Inject styles
	echo '<style type="text/css">' . $smd_redir_styles['list'] . '</style>';

	// Inject Drag n drop jQuery interface
	echo smd_redir_dragdrop();

	$ftypes = array(
		'smd_redir_src' => smd_redir_gTxt('smd_redir_source'),
		'smd_redir_dest' => smd_redir_gTxt('smd_redir_destination'),
	);

	// Control panel
	echo '<div id="smd_container">';
	echo '<fieldset id="smd_redir_control_panel" class="txp-control-panel"><legend class="plain lever'.(get_pref('pane_smd_redir_cpanel_visible') ? ' expanded' : '').'"><a href="#smd_redir_cpanel">'.smd_redir_gTxt('smd_redir_control_panel').'</a></legend>';
	echo '<div id="smd_redir_cpanel" class="toggle" style="display:'.(get_pref('pane_smd_redir_cpanel_visible') ? 'block' : 'none').'">';

	echo '<form id="smd_redir_filtform" action="index.php" method="post">';
	echo '<label for="smd_redir_search">'.smd_redir_gTxt('smd_redir_search').'</label>'
		.'<span id="smd_redir_searchby">'
			.selectInput('smd_redir_filt', $ftypes, '', 0, '', 'smd_redir_filt')
		.'</span>'
		.fInput('text', 'smd_redir_search', '', '', '', '', '', '', 'smd_redir_search')
		.$prefbtn;
	echo eInput($smd_redir_event).sInput('smd_redir_filter');
	echo '</form>';

	echo '</div>';
	echo '</fieldset>';

	// Redirect list
	echo n.'<div id="'.$smd_redir_event.'_container" class="txp-container txp-list">';
	echo '<form name="smd_redir_form" id="smd_redir_form" action="index.php" method="post">';
	echo '<ul id="smd_redir_btnpanel">';
	echo n.'<li id="smd_redir_buttons">' . $newbtn . sp . $status . '</li>';
	echo '<li id="smd_redir_create" class="smd_hidden">'
			.'<label for="smd_redir_newsource">' . smd_redir_gTxt('smd_redir_source') . '</label>'.fInput('text', 'smd_redir_newsource', '', 'smd_focus', '', '', '70', '' ,'smd_redir_newsource')
			.br.'<label for="smd_redir_destination">' . smd_redir_gTxt('smd_redir_destination') . '</label>'.fInput('text', 'smd_redir_destination', '', '', '', '', '70', '' ,'smd_redir_destination')
			.fInput('submit', 'smd_redir_add', gTxt('add'), 'smallerbox', '', '', '', '', 'smd_redir_add')
			.eInput($smd_redir_event)
			.sInput('smd_redir_create')
			.tInput();
	echo '</li></ul>';
	echo '</form>';

	// Remaining redirects
	echo '<div id="smd_redir_data"></div>';
	echo '<ul id="smd_redirects">';
	foreach ($redirects as $idx => $items) {
		echo '<li>
			<span class="smd_redir_grab">&#8657;<br />&#8659;</span>
			<input type="hidden" name="smd_redir_src_orig" value="'.$items['src'].'" />
			<input type="hidden" name="smd_redir_dest_orig" value="'.$items['dst'].'" />
			<div class="smd_redir_item">
			<div class="smd_redir_src closed">'.$items['src'].'</div>
			<div class="smd_redir_dest">'.$items['dst']. '</div>
         </div>
			</li>';
	}
	echo '</ul>';

	echo '</div>';
}

// Create a redirect from the admin side's 'New' button
function smd_redir_create() {
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

		// Redirect doesn't already exist so add it
		if ($found==0) {
			$out[] = array('src' => $smd_redir_newsource, 'dst' => $smd_redir_destination);
		}

		set_pref('smd_redirects', smd_redir_serialize($out), 'smd_redir', PREF_HIDDEN, '', 0);

		$msg = smd_redir_gTxt('smd_redir_added');
	} else {
		$msg = array(smd_redir_gTxt('smd_redir_err_need_source'), E_ERROR);
	}
	smd_redir($msg);
}

// Save the give list of redirects to the prefs array
function smd_redir_save() {
	$data = json_decode(ps('smd_redir_data'), true);
	$out = array();
	foreach($data as $orig => $items) {
		$out[] = array('src' => $items['from'], 'dst' => $items['dest']);
	}

	set_pref('smd_redirects', smd_redir_serialize($out), 'smd_redir', PREF_HIDDEN, '', 0);
	send_xml_response();
}

function smd_redir_get($force=0) {
	$redirects = ($force) ? safe_field('val', 'txp_prefs', 'name="smd_redirects"') : get_pref('smd_redirects', array());
	$redirects = ($redirects) ? smd_redir_unserialize($redirects) : array();

	return $redirects;
}

// Safe-ify saved pref values, since we're dealing with preg_match patterns
function smd_redir_serialize($obj) {
	$crush = smd_redir_check_crush();
	return chunk_split(base64_encode( ( ($crush) ? gzcompress(serialize($obj)) : serialize($obj) ) ));
}
function smd_redir_unserialize($txt) {
	$crush = smd_redir_check_crush();
	return unserialize( ( ($crush) ? gzuncompress(base64_decode($txt)) : base64_decode($txt) ) );
}
function smd_redir_check_crush() {
	return (function_exists('gzcompress') && function_exists('gzuncompress'));
}

// Prefs panel
function smd_redir_prefs($msg='') {
	pagetop(smd_redir_gTxt('smd_redir_tab_name'), $msg);
echo '<p>Coming soon</p>';
}

// TODO: Needed?
// Change and store qty-per-page value
function smd_redir_change_pageby() {
	event_change_pageby('smd_redir');
	smd_redir();
}

// The search dropdown list
function smd_redir_search_form($crit, $method) {
	global $smd_redir_event;

	$methods =	array(
		'source'      => smd_redir_gTxt('smd_redir_source'),
		'destination' => smd_redir_gTxt('smd_redir_destination'),
	);

	return search_form($smd_redir_event, '', $crit, $methods, $method, 'source');
}

// -------------------------------------------------------------
function smd_redir_save_pane_state() {
	$panes = array('smd_redir_cpanel');
	$pane = gps('pane');
	if (in_array($pane, $panes)) {
		set_pref("pane_{$pane}_visible", (gps('visible') == 'true' ? '1' : '0'), 'smd_redir', PREF_HIDDEN, 'yesnoradio', 0, PREF_PRIVATE);
		send_xml_response();
	} else {
		send_xml_response(array('http-status' => '400 Bad Request'));
	}
}

// Just a base64-encoded version of DragSort (http://dragsort.codeplex.com/)
function smd_redir_dragdrop() {
	return '<script type="text/javascript">' . base64_decode('
Ly8galF1ZXJ5IExpc3QgRHJhZ1NvcnQgdjAuNC4zCi8vIExpY2Vuc2U6IGh0dHA6Ly9kcmFnc29y
dC5jb2RlcGxleC5jb20vbGljZW5zZQooZnVuY3Rpb24oYil7Yi5mbi5kcmFnc29ydD1mdW5jdGlv
bihrKXt2YXIgZD1iLmV4dGVuZCh7fSxiLmZuLmRyYWdzb3J0LmRlZmF1bHRzLGspLGc9W10sYT1u
dWxsLGo9bnVsbDt0aGlzLnNlbGVjdG9yJiZiKCJoZWFkIikuYXBwZW5kKCI8c3R5bGUgdHlwZT0n
dGV4dC9jc3MnPiIrKHRoaXMuc2VsZWN0b3Iuc3BsaXQoIiwiKS5qb2luKCIgIitkLmRyYWdTZWxl
Y3RvcisiLCIpKyIgIitkLmRyYWdTZWxlY3RvcikrIiB7IGN1cnNvcjogcG9pbnRlcjsgfTwvc3R5
bGU+Iik7dGhpcy5lYWNoKGZ1bmN0aW9uKGssaSl7YihpKS5pcygidGFibGUiKSYmYihpKS5jaGls
ZHJlbigpLnNpemUoKT09MSYmYihpKS5jaGlsZHJlbigpLmlzKCJ0Ym9keSIpJiYoaT1iKGkpLmNo
aWxkcmVuKCkuZ2V0KDApKTt2YXIgbT17ZHJhZ2dlZEl0ZW06bnVsbCxwbGFjZUhvbGRlckl0ZW06
bnVsbCxwb3M6bnVsbCxvZmZzZXQ6bnVsbCxvZmZzZXRMaW1pdDpudWxsLHNjcm9sbDpudWxsLGNv
bnRhaW5lcjppLGluaXQ6ZnVuY3Rpb24oKXtiKHRoaXMuY29udGFpbmVyKS5hdHRyKCJkYXRhLWxp
c3RJZHgiLCBrKS5tb3VzZWRvd24odGhpcy5ncmFiSXRlbSkuZmluZChkLmRyYWdTZWxlY3Rvciku
Y3NzKCJjdXJzb3IiLCJwb2ludGVyIik7Yih0aGlzLmNvbnRhaW5lcikuY2hpbGRyZW4oZC5pdGVt
U2VsZWN0b3IpLmVhY2goZnVuY3Rpb24oYSl7Yih0aGlzKS5hdHRyKCJkYXRhLWl0ZW1JZHgiLGEp
fSl9LGdyYWJJdGVtOmZ1bmN0aW9uKGUpe2lmKCEoZS53aGljaCE9MXx8YihlLnRhcmdldCkuaXMo
ZC5kcmFnU2VsZWN0b3JFeGNsdWRlKSkpe2Zvcih2YXIgYz1lLnRhcmdldDshYihjKS5pcygiW2Rh
dGEtbGlzdElkeD0nIitiKHRoaXMpLmF0dHIoImRhdGEtbGlzdElkeCIpKyInXSAiK2QuZHJhZ1Nl
bGVjdG9yKTspe2lmKGM9PXRoaXMpcmV0dXJuO2M9Yy5wYXJlbnROb2RlfWEhPW51bGwmJmEuZHJh
Z2dlZEl0ZW0hPW51bGwmJmEuZHJvcEl0ZW0oKTtiKGUudGFyZ2V0KS5jc3MoImN1cnNvciIsIm1v
dmUiKTthPWdbYih0aGlzKS5hdHRyKCJkYXRhLWxpc3RJZHgiKV07YS5kcmFnZ2VkSXRlbT0gYihj
KS5jbG9zZXN0KGQuaXRlbVNlbGVjdG9yKTt2YXIgYz1wYXJzZUludChhLmRyYWdnZWRJdGVtLmNz
cygibWFyZ2luVG9wIikpLGY9cGFyc2VJbnQoYS5kcmFnZ2VkSXRlbS5jc3MoIm1hcmdpbkxlZnQi
KSk7YS5vZmZzZXQ9YS5kcmFnZ2VkSXRlbS5vZmZzZXQoKTthLm9mZnNldC50b3A9ZS5wYWdlWS1h
Lm9mZnNldC50b3ArKGlzTmFOKGMpPzA6YyktMTthLm9mZnNldC5sZWZ0PWUucGFnZVgtYS5vZmZz
ZXQubGVmdCsoaXNOYU4oZik/MDpmKS0xO2lmKCFkLmRyYWdCZXR3ZWVuKWM9YihhLmNvbnRhaW5l
cikub3V0ZXJIZWlnaHQoKT09MD9NYXRoLm1heCgxLE1hdGgucm91bmQoMC41K2IoYS5jb250YWlu
ZXIpLmNoaWxkcmVuKGQuaXRlbVNlbGVjdG9yKS5zaXplKCkqYS5kcmFnZ2VkSXRlbS5vdXRlcldp
ZHRoKCkvYihhLmNvbnRhaW5lcikub3V0ZXJXaWR0aCgpKSkqYS5kcmFnZ2VkSXRlbS5vdXRlckhl
aWdodCgpOmIoYS5jb250YWluZXIpLm91dGVySGVpZ2h0KCksYS5vZmZzZXRMaW1pdD0gYihhLmNv
bnRhaW5lcikub2Zmc2V0KCksYS5vZmZzZXRMaW1pdC5yaWdodD1hLm9mZnNldExpbWl0LmxlZnQr
YihhLmNvbnRhaW5lcikub3V0ZXJXaWR0aCgpLWEuZHJhZ2dlZEl0ZW0ub3V0ZXJXaWR0aCgpLGEu
b2Zmc2V0TGltaXQuYm90dG9tPWEub2Zmc2V0TGltaXQudG9wK2MtYS5kcmFnZ2VkSXRlbS5vdXRl
ckhlaWdodCgpO3ZhciBjPWEuZHJhZ2dlZEl0ZW0uaGVpZ2h0KCksZj1hLmRyYWdnZWRJdGVtLndp
ZHRoKCksaD1hLmRyYWdnZWRJdGVtLmF0dHIoInN0eWxlIik7YS5kcmFnZ2VkSXRlbS5hdHRyKCJk
YXRhLW9yaWdTdHlsZSIsaD9oOiIiKTtkLml0ZW1TZWxlY3Rvcj09InRyIj8oYS5kcmFnZ2VkSXRl
bS5jaGlsZHJlbigpLmVhY2goZnVuY3Rpb24oKXtiKHRoaXMpLndpZHRoKGIodGhpcykud2lkdGgo
KSl9KSxhLnBsYWNlSG9sZGVySXRlbT1hLmRyYWdnZWRJdGVtLmNsb25lKCkuYXR0cigiZGF0YS1w
bGFjZUhvbGRlciIsITApLGEuZHJhZ2dlZEl0ZW0uYWZ0ZXIoYS5wbGFjZUhvbGRlckl0ZW0pLCBh
LnBsYWNlSG9sZGVySXRlbS5jaGlsZHJlbigpLmVhY2goZnVuY3Rpb24oKXtiKHRoaXMpLmNzcyh7
Ym9yZGVyV2lkdGg6MCx3aWR0aDpiKHRoaXMpLndpZHRoKCkrMSxoZWlnaHQ6Yih0aGlzKS5oZWln
aHQoKSsxfSkuaHRtbCgiJm5ic3A7Iil9KSk6KGEuZHJhZ2dlZEl0ZW0uYWZ0ZXIoZC5wbGFjZUhv
bGRlclRlbXBsYXRlKSxhLnBsYWNlSG9sZGVySXRlbT1hLmRyYWdnZWRJdGVtLm5leHQoKS5jc3Mo
e2hlaWdodDpjLHdpZHRoOmZ9KS5hdHRyKCJkYXRhLXBsYWNlSG9sZGVyIiwhMCkpO2EuZHJhZ2dl
ZEl0ZW0uY3NzKHtwb3NpdGlvbjoiYWJzb2x1dGUiLG9wYWNpdHk6MC44LCJ6LWluZGV4Ijo5OTks
aGVpZ2h0OmMsd2lkdGg6Zn0pO2IoZykuZWFjaChmdW5jdGlvbihhLGIpe2IuY3JlYXRlRHJvcFRh
cmdldHMoKTtiLmJ1aWxkUG9zaXRpb25UYWJsZSgpfSk7YS5zY3JvbGw9e21vdmVYOjAsbW92ZVk6
MCxtYXhYOmIoZG9jdW1lbnQpLndpZHRoKCktYih3aW5kb3cpLndpZHRoKCksIG1heFk6Yihkb2N1
bWVudCkuaGVpZ2h0KCktYih3aW5kb3cpLmhlaWdodCgpfTthLnNjcm9sbC5zY3JvbGxZPXdpbmRv
dy5zZXRJbnRlcnZhbChmdW5jdGlvbigpe2lmKGQuc2Nyb2xsQ29udGFpbmVyIT13aW5kb3cpYihk
LnNjcm9sbENvbnRhaW5lcikuc2Nyb2xsVG9wKGIoZC5zY3JvbGxDb250YWluZXIpLnNjcm9sbFRv
cCgpK2Euc2Nyb2xsLm1vdmVZKTtlbHNle3ZhciBjPWIoZC5zY3JvbGxDb250YWluZXIpLnNjcm9s
bFRvcCgpO2lmKGEuc2Nyb2xsLm1vdmVZPjAmJmM8YS5zY3JvbGwubWF4WXx8YS5zY3JvbGwubW92
ZVk8MCYmYz4wKWIoZC5zY3JvbGxDb250YWluZXIpLnNjcm9sbFRvcChjK2Euc2Nyb2xsLm1vdmVZ
KSxhLmRyYWdnZWRJdGVtLmNzcygidG9wIixhLmRyYWdnZWRJdGVtLm9mZnNldCgpLnRvcCthLnNj
cm9sbC5tb3ZlWSsxKX19LDEwKTthLnNjcm9sbC5zY3JvbGxYPXdpbmRvdy5zZXRJbnRlcnZhbChm
dW5jdGlvbigpe2lmKGQuc2Nyb2xsQ29udGFpbmVyIT13aW5kb3cpYihkLnNjcm9sbENvbnRhaW5l
cikuc2Nyb2xsTGVmdChiKGQuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxMZWZ0KCkrIGEuc2Nyb2xs
Lm1vdmVYKTtlbHNle3ZhciBjPWIoZC5zY3JvbGxDb250YWluZXIpLnNjcm9sbExlZnQoKTtpZihh
LnNjcm9sbC5tb3ZlWD4wJiZjPGEuc2Nyb2xsLm1heFh8fGEuc2Nyb2xsLm1vdmVYPDAmJmM+MCli
KGQuc2Nyb2xsQ29udGFpbmVyKS5zY3JvbGxMZWZ0KGMrYS5zY3JvbGwubW92ZVgpLGEuZHJhZ2dl
ZEl0ZW0uY3NzKCJsZWZ0IixhLmRyYWdnZWRJdGVtLm9mZnNldCgpLmxlZnQrYS5zY3JvbGwubW92
ZVgrMSl9fSwxMCk7YS5zZXRQb3MoZS5wYWdlWCxlLnBhZ2VZKTtiKGRvY3VtZW50KS5iaW5kKCJz
ZWxlY3RzdGFydCIsYS5zdG9wQnViYmxlKTtiKGRvY3VtZW50KS5iaW5kKCJtb3VzZW1vdmUiLGEu
c3dhcEl0ZW1zKTtiKGRvY3VtZW50KS5iaW5kKCJtb3VzZXVwIixhLmRyb3BJdGVtKTtkLnNjcm9s
bENvbnRhaW5lciE9d2luZG93JiZiKHdpbmRvdykuYmluZCgiRE9NTW91c2VTY3JvbGwgbW91c2V3
aGVlbCIsYS53aGVlbCk7cmV0dXJuITF9fSxzZXRQb3M6ZnVuY3Rpb24oZSwgYyl7dmFyIGY9Yy10
aGlzLm9mZnNldC50b3AsaD1lLXRoaXMub2Zmc2V0LmxlZnQ7ZC5kcmFnQmV0d2Vlbnx8KGY9TWF0
aC5taW4odGhpcy5vZmZzZXRMaW1pdC5ib3R0b20sTWF0aC5tYXgoZix0aGlzLm9mZnNldExpbWl0
LnRvcCkpLGg9TWF0aC5taW4odGhpcy5vZmZzZXRMaW1pdC5yaWdodCxNYXRoLm1heChoLHRoaXMu
b2Zmc2V0TGltaXQubGVmdCkpKTt0aGlzLmRyYWdnZWRJdGVtLnBhcmVudHMoKS5lYWNoKGZ1bmN0
aW9uKCl7aWYoYih0aGlzKS5jc3MoInBvc2l0aW9uIikhPSJzdGF0aWMiJiYoIWIuYnJvd3Nlci5t
b3ppbGxhfHxiKHRoaXMpLmNzcygiZGlzcGxheSIpIT0idGFibGUiKSl7dmFyIGE9Yih0aGlzKS5v
ZmZzZXQoKTtmLT1hLnRvcDtoLT1hLmxlZnQ7cmV0dXJuITF9fSk7aWYoZC5zY3JvbGxDb250YWlu
ZXI9PXdpbmRvdyljLT1iKHdpbmRvdykuc2Nyb2xsVG9wKCksZS09Yih3aW5kb3cpLnNjcm9sbExl
ZnQoKSxjPU1hdGgubWF4KDAsYy1iKHdpbmRvdykuaGVpZ2h0KCkrIDUpK01hdGgubWluKDAsYy01
KSxlPU1hdGgubWF4KDAsZS1iKHdpbmRvdykud2lkdGgoKSs1KStNYXRoLm1pbigwLGUtNSk7ZWxz
ZSB2YXIgbD1iKGQuc2Nyb2xsQ29udGFpbmVyKSxnPWwub2Zmc2V0KCksYz1NYXRoLm1heCgwLGMt
bC5oZWlnaHQoKS1nLnRvcCkrTWF0aC5taW4oMCxjLWcudG9wKSxlPU1hdGgubWF4KDAsZS1sLndp
ZHRoKCktZy5sZWZ0KStNYXRoLm1pbigwLGUtZy5sZWZ0KTthLnNjcm9sbC5tb3ZlWD1lPT0wPzA6
ZSpkLnNjcm9sbFNwZWVkL01hdGguYWJzKGUpO2Euc2Nyb2xsLm1vdmVZPWM9PTA/MDpjKmQuc2Ny
b2xsU3BlZWQvTWF0aC5hYnMoYyk7dGhpcy5kcmFnZ2VkSXRlbS5jc3Moe3RvcDpmLGxlZnQ6aH0p
fSx3aGVlbDpmdW5jdGlvbihlKXtpZigoYi5icm93c2VyLnNhZmFyaXx8Yi5icm93c2VyLm1vemls
bGEpJiZhJiZkLnNjcm9sbENvbnRhaW5lciE9d2luZG93KXt2YXIgYz1iKGQuc2Nyb2xsQ29udGFp
bmVyKSxmPWMub2Zmc2V0KCk7ZS5wYWdlWD5mLmxlZnQmJiBlLnBhZ2VYPGYubGVmdCtjLndpZHRo
KCkmJmUucGFnZVk+Zi50b3AmJmUucGFnZVk8Zi50b3ArYy5oZWlnaHQoKSYmKGY9ZS5kZXRhaWw/
ZS5kZXRhaWwqNTplLndoZWVsRGVsdGEvLTIsYy5zY3JvbGxUb3AoYy5zY3JvbGxUb3AoKStmKSxl
LnByZXZlbnREZWZhdWx0KCkpfX0sYnVpbGRQb3NpdGlvblRhYmxlOmZ1bmN0aW9uKCl7dmFyIGE9
dGhpcy5kcmFnZ2VkSXRlbT09bnVsbD9udWxsOnRoaXMuZHJhZ2dlZEl0ZW0uZ2V0KDApLGM9W107
Yih0aGlzLmNvbnRhaW5lcikuY2hpbGRyZW4oZC5pdGVtU2VsZWN0b3IpLmVhY2goZnVuY3Rpb24o
ZCxoKXtpZihoIT1hKXt2YXIgZz1iKGgpLm9mZnNldCgpO2cucmlnaHQ9Zy5sZWZ0K2IoaCkud2lk
dGgoKTtnLmJvdHRvbT1nLnRvcCtiKGgpLmhlaWdodCgpO2cuZWxtPWg7Yy5wdXNoKGcpfX0pO3Ro
aXMucG9zPWN9LGRyb3BJdGVtOmZ1bmN0aW9uKCl7aWYoYS5kcmFnZ2VkSXRlbSE9bnVsbCl7Yihh
LmNvbnRhaW5lcikuZmluZChkLmRyYWdTZWxlY3RvcikuY3NzKCJjdXJzb3IiLCAicG9pbnRlciIp
O2EucGxhY2VIb2xkZXJJdGVtLmJlZm9yZShhLmRyYWdnZWRJdGVtKTt2YXIgZT1hLmRyYWdnZWRJ
dGVtLmF0dHIoImRhdGEtb3JpZ1N0eWxlIik7YS5kcmFnZ2VkSXRlbS5hdHRyKCJzdHlsZSIsZSk7
ZT09IiImJmEuZHJhZ2dlZEl0ZW0ucmVtb3ZlQXR0cigic3R5bGUiKTthLmRyYWdnZWRJdGVtLnJl
bW92ZUF0dHIoImRhdGEtb3JpZ1N0eWxlIik7YS5wbGFjZUhvbGRlckl0ZW0ucmVtb3ZlKCk7Yigi
W2RhdGEtZHJvcFRhcmdldF0iKS5yZW1vdmUoKTt3aW5kb3cuY2xlYXJJbnRlcnZhbChhLnNjcm9s
bC5zY3JvbGxZKTt3aW5kb3cuY2xlYXJJbnRlcnZhbChhLnNjcm9sbC5zY3JvbGxYKTt2YXIgYz0h
MTtiKGcpLmVhY2goZnVuY3Rpb24oKXtiKHRoaXMuY29udGFpbmVyKS5jaGlsZHJlbihkLml0ZW1T
ZWxlY3RvcikuZWFjaChmdW5jdGlvbihhKXtwYXJzZUludChiKHRoaXMpLmF0dHIoImRhdGEtaXRl
bUlkeCIpKSE9YSYmKGM9ITAsYih0aGlzKS5hdHRyKCJkYXRhLWl0ZW1JZHgiLCBhKSl9KX0pO2Mm
JmQuZHJhZ0VuZC5hcHBseShhLmRyYWdnZWRJdGVtKTthLmRyYWdnZWRJdGVtPW51bGw7Yihkb2N1
bWVudCkudW5iaW5kKCJzZWxlY3RzdGFydCIsYS5zdG9wQnViYmxlKTtiKGRvY3VtZW50KS51bmJp
bmQoIm1vdXNlbW92ZSIsYS5zd2FwSXRlbXMpO2IoZG9jdW1lbnQpLnVuYmluZCgibW91c2V1cCIs
YS5kcm9wSXRlbSk7ZC5zY3JvbGxDb250YWluZXIhPXdpbmRvdyYmYih3aW5kb3cpLnVuYmluZCgi
RE9NTW91c2VTY3JvbGwgbW91c2V3aGVlbCIsYS53aGVlbCk7cmV0dXJuITF9fSxzdG9wQnViYmxl
OmZ1bmN0aW9uKCl7cmV0dXJuITF9LHN3YXBJdGVtczpmdW5jdGlvbihlKXtpZihhLmRyYWdnZWRJ
dGVtPT1udWxsKXJldHVybiExO2Euc2V0UG9zKGUucGFnZVgsZS5wYWdlWSk7Zm9yKHZhciBjPWEu
ZmluZFBvcyhlLnBhZ2VYLGUucGFnZVkpLGY9YSxoPTA7Yz09LTEmJmQuZHJhZ0JldHdlZW4mJmg8
Zy5sZW5ndGg7aCsrKWM9Z1toXS5maW5kUG9zKGUucGFnZVgsIGUucGFnZVkpLGY9Z1toXTtpZihj
PT0tMXx8YihmLnBvc1tjXS5lbG0pLmF0dHIoImRhdGEtcGxhY2VIb2xkZXIiKSlyZXR1cm4hMTtq
PT1udWxsfHxqLnRvcD5hLmRyYWdnZWRJdGVtLm9mZnNldCgpLnRvcHx8ai5sZWZ0PmEuZHJhZ2dl
ZEl0ZW0ub2Zmc2V0KCkubGVmdD9iKGYucG9zW2NdLmVsbSkuYmVmb3JlKGEucGxhY2VIb2xkZXJJ
dGVtKTpiKGYucG9zW2NdLmVsbSkuYWZ0ZXIoYS5wbGFjZUhvbGRlckl0ZW0pO2IoZykuZWFjaChm
dW5jdGlvbihhLGIpe2IuY3JlYXRlRHJvcFRhcmdldHMoKTtiLmJ1aWxkUG9zaXRpb25UYWJsZSgp
fSk7aj1hLmRyYWdnZWRJdGVtLm9mZnNldCgpO3JldHVybiExfSxmaW5kUG9zOmZ1bmN0aW9uKGEs
Yil7Zm9yKHZhciBkPTA7ZDx0aGlzLnBvcy5sZW5ndGg7ZCsrKWlmKHRoaXMucG9zW2RdLmxlZnQ8
YSYmdGhpcy5wb3NbZF0ucmlnaHQ+YSYmdGhpcy5wb3NbZF0udG9wPGImJnRoaXMucG9zW2RdLmJv
dHRvbT5iKXJldHVybiBkO3JldHVybi0xfSwgY3JlYXRlRHJvcFRhcmdldHM6ZnVuY3Rpb24oKXtk
LmRyYWdCZXR3ZWVuJiZiKGcpLmVhY2goZnVuY3Rpb24oKXt2YXIgZD1iKHRoaXMuY29udGFpbmVy
KS5maW5kKCJbZGF0YS1wbGFjZUhvbGRlcl0iKSxjPWIodGhpcy5jb250YWluZXIpLmZpbmQoIltk
YXRhLWRyb3BUYXJnZXRdIik7ZC5zaXplKCk+MCYmYy5zaXplKCk+MD9jLnJlbW92ZSgpOmQuc2l6
ZSgpPT0wJiZjLnNpemUoKT09MCYmKGIodGhpcy5jb250YWluZXIpLmFwcGVuZChhLnBsYWNlSG9s
ZGVySXRlbS5yZW1vdmVBdHRyKCJkYXRhLXBsYWNlSG9sZGVyIikuY2xvbmUoKS5hdHRyKCJkYXRh
LWRyb3BUYXJnZXQiLCEwKSksYS5wbGFjZUhvbGRlckl0ZW0uYXR0cigiZGF0YS1wbGFjZUhvbGRl
ciIsITApKX0pfX07bS5pbml0KCk7Zy5wdXNoKG0pfSk7cmV0dXJuIHRoaXN9O2IuZm4uZHJhZ3Nv
cnQuZGVmYXVsdHM9e2l0ZW1TZWxlY3RvcjoibGkiLGRyYWdTZWxlY3RvcjoibGkiLGRyYWdTZWxl
Y3RvckV4Y2x1ZGU6ImlucHV0LCB0ZXh0YXJlYSwgYVtocmVmXSIsIGRyYWdFbmQ6ZnVuY3Rpb24o
KXt9LGRyYWdCZXR3ZWVuOiExLHBsYWNlSG9sZGVyVGVtcGxhdGU6IjxsaT4mbmJzcDs8L2xpPiIs
c2Nyb2xsQ29udGFpbmVyOndpbmRvdyxzY3JvbGxTcGVlZDo1fX0pKGpRdWVyeSk7
') . '</script>';
}

//**********************
// PUBLIC SIDE INTERFACE
//**********************
// Todo: is this the most efficient way of doing it?
//   Set a pref for 'default_site' (could be another domain)
function smd_redirect() {
	global $pretext, $siteurl;

	$debug = gps('smd_debug');

	$the_url = parse_url($pretext['request_uri']);
	$intended = $the_url['path'] . (isset($the_url['query']) ? '?'.$the_url['query'] : '');
	if ($debug) {
		echo '++ URL / PATH TO MATCH ++';
		dmp($the_url, $intended);
	}

	// Can't use get_pref() on 404 pages *shrug*
	$redirects = smd_redir_get(1);
	$dflt_location = get_pref('smd_redir_default_site', hu);

	foreach ($redirects as $idx => $items) {
		// Rule can't redirect to itself: ignore
		if ($items['src'] == $items['dst']) continue;

		// Add pattern delimiters.
		// Detect first possible delim char that is not in use in the regex itself.
		$dlmPool = array('`', '!', '@', '|', '#', '~', '%', '/');
		$dlm = array_merge(array_diff($dlmPool, preg_split('//', $items['src'], -1)));
		$pat = (count($dlm) > 0) ? $dlm[0].$items['src'].$dlm[0] : $items['src'];
		if (preg_match($pat, $intended, $matches)) {
			$redir = $items['dst'];
			if ($debug) {
				echo '++ MATCHED PATTERN / REDIRECT RULE ++';
				dmp($pat, $redir);
			}

			$reps = array();
			foreach ($matches as $idx => $input) {
				if ($idx==0) continue; // Don't care about full matched pattern
				$reps['{$'.$idx.'}'] = $input;
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
				//TODO: Make these a pref
				header('Cache-Control: private, no-cache, must-revalidate');
				header('Location:'.$redir, true, 301);
				die();
			}
		}
	}
}

function smd_redir_gTxt($what, $atts = array()) {
	$lang = array(
		'en-gb' => array(
			'smd_redir_added'           => 'Redirect added',
			'smd_redir_btn_new'         => 'New redirect',
			'smd_redir_btn_pref'        => 'Prefs',
			'smd_redir_control_panel'   => 'Control panel',
			'smd_redir_deleting'        => ' Deleting...',
			'smd_redir_destination'     => 'Destination ',
			'smd_redir_err_need_source' => 'You must supply a source URL',
			'smd_redir_saving'          => ' Saving...',
			'smd_redir_search'          => 'Search',
			'smd_redir_source'          => 'Source ',
			'smd_redir_tab_name'        => 'Redirects',
			'smd_redir_updating'        => ' Updating...',
		),
	);

	$thislang = get_pref('language', 'en-gb');
	$thislang = (isset($lang[$thislang][$what])) ? $thislang : 'en-gb';
	return strtr($lang[$thislang][$what], $atts);
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
#smd_help { line-height:1.5 ;}
#smd_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #f0e68c; color:#333; }
#smd_help code.block { font-weight:normal; border:1px dotted #999; display:block; margin:10px 10px 20px; padding:10px; }
#smd_help h1 { font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
#smd_help h2 { border-bottom: 1px solid black; padding:10px 0 0; font: 17px Georgia, sans-serif; }
#smd_help h3 { font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0; text-decoration:underline; }
#smd_help h4 { font: bold 11px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0; text-transform: uppercase; }
#smd_help .atnm { font-weight:bold; }
#smd_help .mand { background:#eee; border:1px dotted #999; }
#smd_help table { width:90%; text-align:center; padding-bottom:1em; border-collapse:collapse; }
#smd_help td, #smd_help th { border:1px solid #999; padding:.5em; }
#smd_help ul { list-style-type:square; }
#smd_help .important { color:red; }
#smd_help li { margin:5px 20px 5px 30px; }
#smd_help .break { margin-top:5px; }
#smd_help dl dd { margin:2px 15px; }
#smd_help dl dd:before { content: "\21d2\00a0"; }
#smd_help dl dd dl { padding: 0 15px; }
</style>
# --- END PLUGIN CSS ---
-->
<!--
# --- BEGIN PLUGIN HELP ---
notextile. <div id="smd_help">

h1. smd_redirect

Redirect one URL to another _without_ requiring @.htaccess@. Supports standard regular expression wildcard matches.

Add rules using _Extensions -> Redirects_. Click 'New redirect' and enter a URL portion to match against, and then a destination for that URL.

* Source and destination can either be:
** relative (no preceeding slash).
** root-relative (with preceding slash).
** absolute (full URL including domain).
* Source can be anchored if you specify the regex start (^) and/or end ($) anchor characters.
* Use an empty destination to redirect to site root (pref coming soon to redirect to arbitrary URL).
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

notextile. </div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>