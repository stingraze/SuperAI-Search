from bs4 import BeautifulSoup
import urllib2
import urllib2 as req
import re
#input from STDIN 
input_url = raw_input()
#open URL for BeautifulSoup to parse
resource = req.urlopen(input_url)


#Needs work with Error 999 Exception.
soup = BeautifulSoup(resource, "html.parser")

links = soup.find_all("a")

for a in links: 
	href = a.attrs['href']
	text = a.string
	print href.encode('utf-8')
	if "http" in href:
		try:
			new_resource = req.urlopen(href)
			new_soup = BeautifulSoup(new_resource, "html.parser")
			links2 = new_soup.find_all("a")
			print "New Resource"
		except urllib2.HTTPError as err:
			if err.code == 404:
    				print "404 Error, Skipped"
				continue
			if err.code == 999:
				print "999 (LinkedIn?) Error, Skipped"
				continue
			else:
				continue