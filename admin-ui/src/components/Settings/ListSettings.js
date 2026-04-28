import React from 'react';
import ListSelect from "../Common/ListSelect";
import DropdownWrapper from "../Common/DropdownWrapper";
import { getChosenLabel } from "../../helpers/utilities";
import { UiLabelContext } from "../../Context";

const ListSettings = ({ isConnected, settings, setSettings, selectedList, setSelectedList, handleSearch, lists, nextOffset, totalLists, listsLoading, isAutoFetching, errorList, setErrorList, listTransition }) => {
    const labels = React.useContext(UiLabelContext);

    if (!isConnected) {
        return null;
    }

    return (
        <div className="wlmi-flex wlmi-flex-col wlmi-w-full">
            {/* List Selection */}
            <div className="wlmi-flex wlmi-flex-col wlmi-w-full wlmi-mt-5">
                <label className="wlmi-text-dark wlmi-font-medium wlmi-text-sm wlmi-mb-2">
                    {labels.settings?.list_label || "Select Mailchimp List"}
                </label>
                <ListSelect
                    id="mailchimp_list"
                    value={selectedList}
                    onChange={(selected) => {
                        setSelectedList(selected);
                        setSettings({
                            ...settings,
                            list_id: selected ? selected.value : "",
                            migration_choice: ""
                        });
                        setErrorList((prev) => prev.filter((key) => key !== "migration_choice"));
                    }}
                    onSearch={handleSearch}
                    options={lists}
                    hasMore={nextOffset < totalLists}
                    loading={listsLoading || isAutoFetching}
                    error={errorList.includes("list_id")}
                    placeholder={labels.settings?.list_placeholder || "Search or select a list"}
                    searchPlaceholder={labels.settings?.search_placeholder || "Type to search lists..."}
                    loadingMessage={isAutoFetching
                        ? (labels.settings?.searching_message || "Searching through lists...")
                        : (labels.settings?.loading_message || "Loading...")}
                    noOptionsMessage={labels.settings?.no_results_message || "No lists found"}
                    scrollForMoreMessage={labels.settings?.scroll_for_more_message || "Scroll for more..."}
                />
                <p className="wlmi-text-xs wlmi-text-light wlmi-mt-1">
                    {labels.settings?.list_description || "Choose the Mailchimp list where customers will be added"}
                </p>
                {isAutoFetching && (
                    <p className="wlmi-text-xs wlmi-text-primary wlmi-mt-1 wlmi-font-medium">
                        🔍 {(labels.settings?.searching_progress_message || "Searching through %s lists...").replace('%s', totalLists)}
                    </p>
                )}
            </div>

            {/* Migration Choice Dropdown */}
            {listTransition && settings.list_id && (
                <div className="wlmi-flex wlmi-flex-col wlmi-w-full wlmi-mt-5">
                    <label className="wlmi-text-dark wlmi-font-medium wlmi-text-sm wlmi-mb-2">
                        {labels.settings?.migration_label || "Migration Choice"}
                    </label>
                    <DropdownWrapper
                        options={labels.settings?.migration_options || []}
                        value={settings.migration_choice || ''}
                        handleDropDownClick={(item) => {
                            setSettings({
                                ...settings,
                                migration_choice: item.value
                            });
                            setErrorList((prev) => prev.filter((key) => key !== "migration_choice"));
                        }}
                        label={settings.migration_choice
                            ? getChosenLabel(labels.settings?.migration_options || [], settings.migration_choice) || labels.settings?.migration_placeholder
                            : (labels.settings?.migration_placeholder || "Select migration choice")}
                        width="wlmi-w-full"
                    />
                    <p className="wlmi-text-xs wlmi-text-light wlmi-mt-1">
                        {labels.settings?.migration_description || "Choose your migration option"}
                    </p>
                    {errorList.includes("migration_choice") && (
                        <p className="wlmi-text-xs wlmi-text-red-600 wlmi-mt-1">
                            {labels.settings?.migration_choice_required || "Please choose whether to migrate existing users."}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
};

export default ListSettings;
