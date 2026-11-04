import pathlib

p = pathlib.Path('src/Domain/DomainContext.php')
src = p.read_text(encoding='utf-8')

old = """        public readonly string $scheme = 'www',
    ) {
    }
"""
new = """        public readonly string $scheme = 'www',
    ) {
    }

    public function withAllias(string $allias): self
    {
        return new self(
            domain: $this->domain,
            real: $this->real,
            allias: $allias,
            skin: $this->skin,
            language: $this->language,
            available: $this->available,
            stub: $this->stub,
            namespace: $this->namespace,
            scheme: $this->scheme,
        );
    }
"""
assert old in src, "Pattern not found in DomainContext.php"
assert src.count(old) == 1, "Pattern not unique"
p.write_text(src.replace(old, new, 1), encoding='utf-8')
print("OK: DomainContext::withAllias added.")
