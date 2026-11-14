

## Endpoint API
The endpoints receive:
  * **Body:** Via a post-body mechanism the Latex source to be precompiled.
  * **Parameters:** Via http headers.


### Endpoint Returns:

#### Normal execution (there is a media result and no error)
Endpoint returns the media in the required mime type and a header
X-Parsifal-Error with value "None".

#### Soft error (there is a media result result but some error condition)
Endpoint returns the media in the required mime type and signals that there 
is a soft error condition by adding a header X-Parsifal-Error with value "Soft". 

#### Hard error (there is no media result due to a non-recoverable error)
Endpoint returns media of mime type text which contains an error indication.
To allow the client to distinguish this from a normal execution which returns 
a mime type text, the server adds a header X-Parsifal-Error with value "Hard". 


Note: We may find soft errors in two scenarios: On preview (as described here) 
and on media load. For the case of media load we need an additional 
.mrk marker file, since in this case we obtain the media from 
cache as a file and not freshly made - and in this case the server side
file access might not know about the error status. 

