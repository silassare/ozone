<?php ============================================================ = [
																		 'namespace'      => "SONDXPRESS\App\Services",
																		 'useNamespaces'  => [// contains required namespaces
																		 ],
																		 'serviceName'    => "MyServiceName",
																		 'appPrefix'      => "BX",
																		 'serviceActions' => [// contains ServiceAction object
																		 ]
																	 ]

																	 =============================================================
/**
 * OZone
 * Auto Generated Service please don't edit
 */
	namespace <%
	$.namespace %>;

	<%
	loop($.useNamespaces : $item ) {
			%>

			use <% $item %>;
	<%}%>

	final class <% $.serviceName %> extends OZoneService {
			private
			static $EXTRA_REG = "#^([a-z_]+)#";

			public
			function __construct()
			{
				parent::__construct();
			}

			public
			function execute($request = [])
			{
				$extra_map = ['action'];
				$extra     = [];
				$extra_ok  = OZoneUri::parseUriExtra(self::$EXTRA_REG, $extra_map, $extra);

				OZoneAssert::assertAuthorizeAction($extra_ok, new OZoneForbiddenException('<% $.appPrefix %>_ACTION_IS_INVALID'));

				$action = $extra['action'];

				switch ($action) {
				<% loop($.serviceActions : $action) %>
					case '<% $action->getActionUriSub() %>':
						OZoneAssert::assertSafeRequestMethod(array(<% @oz_quoted_list($action->getSafeRequestMethods()) %>));
					$this-><% $action->getActionFuncName() %>($request);
					break;
				<%
				}
				%>
			default:
				throw new OZoneForbiddenException('<% $.appPrefix %>_ACTION_UNKNOWN');
				break;
			}
		}

		<% loop($.serviceActions : $action ) %>
		private function <% $action->getActionFuncName() %>($request = []){
			<% @oz_indent($action->getActionSourceCode(), 3) %>
		}
		<%}%>
	}