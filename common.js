
/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

function select_checkboxes(curFormId, link, new_string)
{
	var curForm = document.getElementById(curFormId);
	var inputlist = curForm.getElementsByTagName("input");
	for (i = 0; i < inputlist.length; i++)
	{
		if (inputlist[i].getAttribute("type") == 'checkbox' && inputlist[i].disabled == false)
			inputlist[i].checked = true;
	}
	
	link.setAttribute('onclick', 'return unselect_checkboxes(\'' + curFormId + '\', this, \'' + link.innerHTML + '\')');
	link.innerHTML = new_string;

	return false;
}

function unselect_checkboxes(curFormId, link, new_string)
{
	var curForm = document.getElementById(curFormId);
	var inputlist = curForm.getElementsByTagName("input");
	for (i = 0; i < inputlist.length; i++)
	{
		if (inputlist[i].getAttribute("type") == 'checkbox' && inputlist[i].disabled == false)
			inputlist[i].checked = false;
	}
	
	link.setAttribute('onclick', 'return select_checkboxes(\'' + curFormId + '\', this, \'' + link.innerHTML + '\')');
	link.innerHTML = new_string;

	return false;
}
