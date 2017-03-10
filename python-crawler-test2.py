from bs4 import BeautifulSoup
from time import sleep
import urllib2
#input from STDIN 
input_url = raw_input()
#UserAgent info
req = urllib2.Request(input_url, headers={ 'User-Agent' : 'Mozilla/5.0' } )

#open URL for BeautifulSoup to parse
resource = urllib2.urlopen(req).read()

soup = BeautifulSoup(resource, "html.parser")

links = soup.find_all("a")

for a in links: 
	href = a.attrs['href']
	text = a.string
	print href.encode('utf-8')
	#skips twitter
	if 'twitter.com' in href:
		continue
	#skips linkedin
	if 'linkedin.com' in href:
		continue

	if "http" in href:
		try:
			#UserAgent Info
			req2 = urllib2.Request(href, headers={ 'User-Agent' : 'Mozilla/5.0' } )

			new_resource = urllib2.urlopen(req2).read()
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
