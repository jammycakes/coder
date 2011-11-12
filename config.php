<div class="wrap">
	<h2>Code syntax highlighting default options</h2>
	<form method="post" action="" id="coder-conf">
		<p>These options can be overridden for each code block.</p>
		<?php foreach ($this->opts as $k => $v): ?>
			<p>
				<input type="checkbox" id="<?php echo $k; ?>" name="<?php echo $k; ?>" <?php
					if ($this->$k) { echo ' checked="checked"'; } 
					?> />
				<label for="<?php echo $k; ?>">
					<?php echo $v[3]; ?>
				</label>
			</p>
		<?php endforeach; ?>	
		<p class="submit">
			<input type="submit" name="Submit" value="Update Options &raquo;" />
		</p>
	</form>
</div>
