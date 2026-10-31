# Purpose #

DanteDiv adds diverse convenience features to MediaWiki


# Requirements





# Todo 





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