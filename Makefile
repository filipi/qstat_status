clean:	
	find . -iname \*tmpfile -exec rm -rfv {} \;
	find . -iname .\#\* -exec rm -rfv {} \;
	find . -iname ~\* -exec rm -rfv {} \;
	find . -iname \*~ -exec rm -rfv {} \;
	find . -iname .\*~ -exec rm -rfv {} \;
	find . -iname \#\*\# -exec rm -rfv {} \;
	find . -name .htaccess~ -exec rm -rfv {} \;
	if [ -e cscope.out ]; then rm -rvf cscope.out; fi;
	if [ -e *.log ]; then rm -rvf *.log; fi;

distclean:	clean

