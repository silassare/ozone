// <?php

	<%
	if ($.action->isActionRuleFor('admin') ) {
		%>
		OZoneAssert::assertIsAdmin();
		<%
	} else if ($.action->isActionRuleFor('verified') ) {
		%>
		OZoneAssert::assertUserVerified();
		<%
	} else if ($.action->isActionRuleFor('assert')){
		%>
		// run assertion here
		<%
	}%>

$required = array( <% @oz_quoted_list($.tableDesc->getEditableFormFieldsName() ) %> );

OZoneAssert::assertForm($request, $required, new OZoneForbiddenException('OZ_ERROR_INVALID_FORM'));

$tableDesc = new TableDescriptor( <% $.tableDesc->getTableName() %> );

$ofv_obj = new OFormValidator($request);
$ofv_obj->checkForm($tableDesc->getEditableFormFieldsRules());

$safe_form = $ofv_obj->getForm();

$result = <% $.tableDesc->getControllerClassName() %>::create($safe_form);

self::$resp->setDone('<% $.appPrefix %>_<% @oz_toupper( $.tableDesc->getTableName(false) ) %>_ADDED')
		   ->setData($result);