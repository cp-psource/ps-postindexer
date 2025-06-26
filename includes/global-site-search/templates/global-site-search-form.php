<?php global $current_blog ?>
<form id="gss-ajax-search-form" action="#" method="get">
	<table border="0" cellpadding="2" cellspacing="2" style="width:100%">
		<tr>
			<td style="width:80%">
				<input type="text" name="phrase" id="gss-ajax-phrase" style="width:100%" value="<?php echo esc_attr( stripslashes( global_site_search_get_phrase() ) ) ?>">
			</td>
			<td style="text-align:right;width:20%">
				<input type="submit" value="<?php _e( 'Suchen', 'globalsitesearch' ) ?>">
			</td>
		</tr>
	</table>
</form>
<div id="gss-ajax-results"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('gss-ajax-search-form');
  var results = document.getElementById('gss-ajax-results');
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var phrase = document.getElementById('gss-ajax-phrase').value;
    if (!phrase) return;
    results.innerHTML = '<div style="color:#888;">Suche läuft...</div>';
    fetch(window.location.pathname + '?gss_ajax=1&phrase=' + encodeURIComponent(phrase))
      .then(r => r.text())
      .then(html => { results.innerHTML = html; });
  });
});
</script>