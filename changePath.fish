function changePath --argument-names file
	touch parsed.php
	set content (cat $file)
	set content (string replace -r "((\.\.\/){3})+" "./project/" $content)
	set elements (string match -r "ce_[^ |\"]*" $content)
	for i in $elements; 
		#if [ (string match -r "id=\"$i\"" $content) != "" ]
			#echo (string match -r "id=\"$i\"" $content)
			set content (string replace --regex " (?=class=\"$i(\"| ))" " id=\"$i\" " $content);
		#end;
	end;
	echo $content >> parsed.php
end
