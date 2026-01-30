import React from 'react';
import Select from 'react-select';

const ListSelect = ({
    value,
    onChange,
    onLoadMore,
    options,
    hasMore,
    loading,
    error,
    placeholder = "Select a list",
    id
}) => {
    const [menuIsOpen, setMenuIsOpen] = React.useState(false);

    // Add "Load More" option at the end if there are more items
    const enhancedOptions = React.useMemo(() => {
        if (!hasMore || loading) {
            return options;
        }
        return [
            ...options,
            {
                value: '__load_more__',
                label: '🔄 Load More Lists...',
                isLoadMore: true
            }
        ];
    }, [options, hasMore, loading]);

    const handleChange = (selectedOption) => {
        if (selectedOption && selectedOption.isLoadMore) {
            // Trigger load more
            onLoadMore();
            // Keep menu open
            setMenuIsOpen(true);
        } else {
            onChange(selectedOption);
            setMenuIsOpen(false);
        }
    };

    const customStyles = {
        option: (provided, state) => ({
            ...provided,
            backgroundColor: state.data.isLoadMore
                ? '#F6F6FE'
                : state.isSelected
                    ? '#4F47EB'
                    : state.isFocused
                        ? '#F6F6FE'
                        : 'white',
            color: state.data.isLoadMore
                ? '#4F47EB'
                : state.isSelected
                    ? 'white'
                    : '#1D2327',
            fontWeight: state.data.isLoadMore ? '600' : '400',
            cursor: state.data.isLoadMore ? 'pointer' : 'default',
            ':active': {
                backgroundColor: state.data.isLoadMore ? '#E8E7FD' : provided[':active'].backgroundColor,
            },
        }),
    };

    return (
        <div className="w-full" id="input-select-tag">
            <Select
                id={id}
                options={enhancedOptions}
                className={`w-full focus:border-2 focus:border-primary ${error && "input-error"}`}
                value={value}
                onChange={handleChange}
                isSearchable={true}
                classNamePrefix="react_select"
                placeholder={placeholder}
                isLoading={loading}
                menuIsOpen={menuIsOpen}
                onMenuOpen={() => setMenuIsOpen(true)}
                onMenuClose={() => setMenuIsOpen(false)}
                styles={customStyles}
                theme={(theme) => ({
                    ...theme,
                    borderRadius: 6,
                    colors: {
                        ...theme.colors,
                        primary25: '#F6F6FE',
                        primary: '#4F47EB',
                    },
                })}
                noOptionsMessage={() => loading ? 'Loading...' : 'No lists found'}
            />
        </div>
    );
};

export default ListSelect;
