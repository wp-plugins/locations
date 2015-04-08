<div id="content" style="min-width: 250px">
	<h3 id="firstHeading" class="firstHeading" style="margin-top: 0px; margin-bottom: 10px">
		<a href="{{permalink}}" target="_blank">{{title}}</a>
	</h3>
	<div id="bodyContent">
		<p class="addr">
			{{street_address}}
			{{#street_address_line_2}}
			<br />{{street_address_line_2}}
			{{/street_address_line_2}}
			<br />
			{{city}}, {{state}} {{zipcode}}
		</p>
		{{#phone}}
		<p class="phone" style="margin-bottom: 4px"><strong>Phone:</strong> {{phone}}</p>
		{{/phone}}
		{{#fax}}
		<p class="fax" style="margin-bottom: 4px"><strong>Fax:</strong> {{fax}}</p>
		{{/fax}}
		{{#email}}
		<p class="email" style="margin-bottom: 4px"><strong>Email:</strong> <a href="mailto:{{loc.email}}">{{email}}</p>
		{{/email}}
	</div>
</div>