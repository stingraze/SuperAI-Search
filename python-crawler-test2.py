from bs4 import BeautifulSoup
from time import sleep
import urllib2
import urllib2 as req
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
			sleep(1)
			for b in links2: 
				try:
					href2 = b.attrs['href']
					text2 = b.string
					print href2.encode('utf-8')
					sleep(1)
				except:
					continue

		except urllib2.HTTPError as err:
			if err.code == 404:
    				print "404 Error, Skipped"
				continue
			if err.code == 999:
				print "999 (LinkedIn?) Error, Skipped"
				continue
			else:
				continue