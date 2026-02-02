package policy

// AllowedMethods is the strict whitelist for receive-only NWC. No pay_* methods.
// list_transactions is allowed so BTCPay "Test connection" passes; we return empty list.
var AllowedMethods = map[string]bool{
	"make_invoice":      true,
	"lookup_invoice":    true,
	"get_info":         true,
	"list_transactions": true,
}

// Allow returns true only for receive-only methods. pay_invoice and any send method are denied.
func Allow(method string) bool {
	return AllowedMethods[method]
}
