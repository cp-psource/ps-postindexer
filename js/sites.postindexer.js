function pi_loadsiteeditform() {
    tb_show(postindexer.siteedittitle, jQuery(this).attr('href'));
    return false;
}

function pi_loadsitesummary() {
    tb_show(postindexer.sitesummarytitle, jQuery(this).attr('href'));
    return false;
}
/*Depracated .click() fix*/
function pi_sitesready() {
    //jQuery('a.postindexersiteeditlink').click(pi_loadsiteeditform);
    jQuery('a.postindexersiteeditlink').on('click', pi_loadsiteeditform);
    //jQuery('a.postindexersitesummarylink').click(pi_loadsitesummary);
    jQuery('a.postindexersitesummarylink').on('click', pi_loadsitesummary);
}

jQuery(document).ready(pi_sitesready);