function changePath --argument-names file
	touch parsed.php
	set content (cat $file)
	set content (string replace -r "((\.\.\/){3})+" "./project/" $content)
	echo $content >> parsed.php
end
