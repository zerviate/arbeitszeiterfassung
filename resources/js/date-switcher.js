document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-date-switcher]').forEach((switcher) => {
        const input = switcher.querySelector('input')
        const select = switcher.querySelector('select')
        const form = switcher.closest('form')

        if ((!input && !select) || !form) {
            return
        }

        const submit = () => {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit()
                return
            }

            form.submit()
        }

        if (input) {
            input.addEventListener('change', submit)
        }

        if (select) {
            select.addEventListener('change', submit)
        }
    })
})
