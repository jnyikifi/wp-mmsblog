USE lokala;

DROP TABLE mmsblog_alias;

CREATE TABLE mmsblog_alias (
    email VARCHAR(100),
    wp_email VARCHAR(100),
    PRIMARY KEY (email, wp_email)
);

INSERT INTO mmsblog_alias (email, wp_email) VALUES
    ("jny@localhost.jnys-mac.local", "jny@lokala.org"),
    ("+358405216240@mms.soneramail.com", "jny@lokala.org"),
    ("jny@iki.fi", "jny@lokala.org"),
    ("pni@skrubu.net", "pni@iki.fi"),
    ("+358400543880@mms.soneramail.com", "pni@iki.fi"),
    ("+358503571457/TYPE=PLMN@mms.radiolinja.fi", "swana@iki.fi"),
    ("swana@cc.hut.fi", "swana@iki.fi"),
    ("+358405277693@mms.soneramail.com", "n@iki.fi");
