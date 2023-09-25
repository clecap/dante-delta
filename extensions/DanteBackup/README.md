# Purpose #

DanteDiv adds diverse convenience features to MediaWiki


# Requirements





# Todo 






/*
  $descriptorspec = array( 0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w") );
  $cwd = getcwd();
  $env = null;

  $proc = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);
  if (is_resource($proc)) {
    // Output test:
    echo "STDOUT:<br />";
    echo "<pre>".stream_get_contents($pipes[1])."</pre>";
    echo "STDERR:<br />";
    echo "<pre>".stream_get_contents($pipes[2])."</pre>";
    $return_value = proc_close($proc);
    echo "Exited with status: {$return_value}";
  }

*/



# Prepare for AWS S3 use

1) Bucket must have a specific policy
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::499754002549:user/DanteBackup"
            },
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::dantebackup.iuk.one/*"
        },
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::499754002549:user/DanteBackup"
            },
            "Action": "s3:ListBucket",
            "Resource": "arn:aws:s3:::dantebackup.iuk.one"
        },
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::499754002549:user/DanteBackup"
            },
            "Action": "s3:ListBucketVersions",
            "Resource": "arn:aws:s3:::dantebackup.iuk.one"
        }
    ]
}


2) IAM user must have s3 rights on bucket and on bucket/*