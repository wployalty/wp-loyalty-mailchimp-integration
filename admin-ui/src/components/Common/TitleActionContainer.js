import React from 'react';
import Button from "./Button";
import {CommonContext, UiLabelContext} from "../../Context";

const TitleActionContainer = ({ title, saveAction, saveDisabled = false, showSave = true, syncAction, syncDisabled = false, syncLoading = false, showSync = false }) => {
    const labels = React.useContext(UiLabelContext);
    const {appState} = React.useContext(CommonContext);

    return <div className={`wlmi-flex wlmi-items-center wlmi-justify-between wlmi-gap-2 wlmi-w-full`}>
        {/*left div*/}
        <p className={`wlmi-text-dark wlmi-font-bold wlmi-text-md wlmi-tracking-[1.28px]`}>{title}</p>
        {/*right div*/}
        <div className={`wlmi-flex wlmi-items-center wlmi-gap-2.5`}>
            <Button
                icon={<i className={`wlr wlrf-back wlmi-text-md wlmi-font-medium color-important`}/>}
                outline={true}
                bgColor={"wlmi-bg-white"}
                outlineStyle={"wlmi-border-light_border"}
                textStyle={"wlmi-text-light"}
                click={() => location.replace(appState.common.back_to_apps_url)}
            >
                {labels.common.back_to_loyalty}
            </Button>

            {showSync && (
                <Button
                    click={syncAction}
                    disabled={syncDisabled || syncLoading}
                    icon={<i className={`wlr wlrf-refresh wlmi-text-md wlmi-font-medium color-important ${syncLoading ? 'wlmi-animate-spin' : ''}`} />}
                >
                    {labels.settings?.perform_sync || "Perform Sync"}
                </Button>
            )}

            {showSave && (
                <Button
                    icon={<i className={`wlr wlrf-save wlmi-text-md wlmi-font-medium color-important `}/>}
                    click={saveAction}
                    disabled={saveDisabled}
                >
                    {labels.common.save}
                </Button>
            )}
        </div>
    </div>
};

export default TitleActionContainer;