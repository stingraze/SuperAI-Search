from bs4 import BeautifulSoup
import urllib2 as req
import re
#input from STDIN 
input_url = raw_input()
#open URL for BeautifulSoup to parse
resource = req.urlopen(input_url)


#Needs work with Error 999 Exception.
soup = BeautifulSoup(resource, "html.parser")

links = soup.find_all("a")
try:
	for a in links: 
		href = a.attrs['href']
		text = a.string
		print href.encode('utf-8')
		pattern = re.compile('http+')
		pm_flag = pattern.match(href)

		if pm_flag:
			new_resource = req.urlopen(href)
			new_soup = BeautifulSoup(new_resource, "html.parser")
			links2 = new_soup.find_all("a")
			print "New Resource"
finally: 
	try:
		print "Error Skipped"
	except: 
		pass


	
		



