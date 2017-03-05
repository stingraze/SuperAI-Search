from bs4 import BeautifulSoup
import urllib2 as req

input_url = raw_input()

resource = req.urlopen(input_url)

soup = BeautifulSoup(resource, "html.parser")

links = soup.find_all("a")

for a in links: 
	href = a.attrs['href']
	text = a.string
	print href.encode('utf-8')

