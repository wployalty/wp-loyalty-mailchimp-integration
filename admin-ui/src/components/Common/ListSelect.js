import React from 'react';
import Select from 'react-select';

const ListSelect = ({
    value,
    onChange,
    onSearch,
    options,
    hasMore,
    loading,
    error,
    placeholder,
    id,
    searchPlaceholder,
    noOptionsMessage,
    loadingMessage,
    scrollForMoreMessage
}) => {
    const [menuIsOpen, setMenuIsOpen] = React.useState(false);
    const [inputValue, setInputValue] = React.useState('');
    const searchTimeoutRef = React.useRef(null);

    // Debounced search handler
    const handleInputChange = (newValue, { action }) => {
        if (action === 'input-change') {
            setInputValue(newValue);

            // Clear existing timeout
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }

            // Debounce search by 500ms
            searchTimeoutRef.current = setTimeout(() => {
                if (onSearch) {
                    onSearch(newValue);
                }
            }, 500);
        }

        return newValue;
    };

    // Cleanup timeout on unmount
    React.useEffect(() => {
        return () => {
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }
        };
    }, []);

    const customStyles = {
        option: (provided, state) => ({
            ...provided,
            backgroundColor: state.isSelected
                ? '#4F47EB'
                : state.isFocused
                    ? '#F6F6FE'
                    : 'white',
            color: state.isSelected
                ? 'white'
                : '#1D2327',
            cursor: 'pointer',
            ':active': {
                backgroundColor: '#E8E7FD',
            },
        }),
        menu: (provided) => ({
            ...provided,
            zIndex: 9999,
        }),
        menuList: (provided) => ({
            ...provided,
            maxHeight: '300px',
        }),
    };

    // Custom menu list with infinite scroll
    const MenuList = (props) => {
        const menuListRef = React.useRef(null);

        const handleScroll = (e) => {
            const bottom = e.target.scrollHeight - e.target.scrollTop === e.target.clientHeight;
            if (bottom && hasMore && !loading && onSearch) {
                // Trigger load more when scrolled to bottom
                onSearch(inputValue, true); // true indicates "load more"
            }
        };

        return (
            <div
                ref={menuListRef}
                onScroll={handleScroll}
                style={{
                    maxHeight: '300px',
                    overflowY: 'auto',
                }}
            >
                {props.children}
                {loading && (
                    <div style={{
                        padding: '12px',
                        textAlign: 'center',
                        color: '#4F47EB',
                        fontSize: '14px',
                        fontWeight: '500'
                    }}>
                        {loadingMessage}
                    </div>
                )}
                {hasMore && !loading && options.length > 0 && (
                    <div style={{
                        padding: '8px 12px',
                        textAlign: 'center',
                        color: '#6B7280',
                        fontSize: '12px',
                        borderTop: '1px solid #E5E7EB'
                    }}>
                        {scrollForMoreMessage}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="wlmi-w-full" id="input-select-tag">
            <Select
                id={id}
                options={options}
                className={`wlmi-w-full focus:wlmi-border-2 focus:wlmi-border-primary ${error && "wlmi_input-error"}`}
                value={value}
                onChange={(selected) => {
                    onChange(selected);
                    setMenuIsOpen(false);
                }}
                onInputChange={handleInputChange}
                inputValue={inputValue}
                isSearchable={true}
                classNamePrefix="react_select"
                placeholder={placeholder}
                isLoading={loading && options.length === 0}
                menuIsOpen={menuIsOpen}
                onMenuOpen={() => setMenuIsOpen(true)}
                onMenuClose={() => {
                    setMenuIsOpen(false);
                    setInputValue('');
                }}
                // Prevent auto-selection behavior
                blurInputOnSelect={false}
                closeMenuOnScroll={false}
                isClearable={true}
                controlShouldRenderValue={true}
                // Only change value on explicit selection
                tabSelectsValue={false}
                openMenuOnFocus={false}
                styles={customStyles}
                components={{ MenuList }}
                theme={(theme) => ({
                    ...theme,
                    borderRadius: 6,
                    colors: {
                        ...theme.colors,
                        primary25: '#F6F6FE',
                        primary: '#4F47EB',
                    },
                })}
                noOptionsMessage={() => loading ? loadingMessage : noOptionsMessage}
                filterOption={() => true} // Disable client-side filtering since we're doing server-side
            />
        </div>
    );
};

export default ListSelect;
